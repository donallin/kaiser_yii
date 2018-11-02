<?php
/**
 * User: donallin
 */

namespace common\components;

class SqlBuilder
{
    static private $_instance = null;
    public $separator = ' ';

    /**
     * @param string $builder_name
     * @return SqlBuilder $instance
     */
    public static function getInstance($builder_name = 'main')
    {
        if (empty(self::$_instance)) {
            self::$_instance = [];
        }
        if (empty(self::$_instance[$builder_name])) {
            self::$_instance[$builder_name] = new SqlBuilder();

        }
        return self::$_instance[$builder_name];
    }

    /**
     * @param KsMysql $query
     * @param array $params
     * @return array
     */
    public function build($query, $params = [])
    {
        $query = $query->prepare($this);
        $params = empty($params) ? $query->params : array_merge($params, $query->params);
        $clauses = [
            $this->buildSelect($query->sqlQuery['select'], $query->sqlQuery['distinct'], $query->sqlQuery['selectOption']),
            $this->buildFrom($query->sqlQuery['from']),
            $this->buildJoin($query->sqlQuery['join']),
            $this->buildWhere($query->sqlQuery['where'], $params),
            $this->buildGroupBy($query->sqlQuery['groupBy']),
            $this->buildHaving($query->sqlQuery['having'], $params),
            $this->buildOrderBy($query->sqlQuery['orderBy']),
            $this->buildLimit($query->sqlQuery['limit'], $query->sqlQuery['offset'])
        ];
        $sql = implode($this->separator, array_filter($clauses)); // array_filter过滤空值
        $union = $this->buildUnion($query->sqlQuery['union'], $params);
        if ($union !== null) {
            $sql = "($sql){$this->separator}$union";
        }
        $this->formatParams($params); // 后移一位
        return [$sql, $params];
    }

    public function buildSelect($columns, $distinct = false, $selectOption = null)
    {
        $select = $distinct ? 'SELECT DISTINCT' : 'SELECT';
        if ($selectOption !== null) {
            $select .= $this->separator . $selectOption;
        }
        if (empty($columns) || $columns == '*') {
            return $select . " *";
        }
        if (!is_array($columns)) {
            throw new \RuntimeException('select params error(is not array)', ErrorCode::ERR_DB_CODE);
        }
        foreach ($columns as $i => $column) {
            if (is_int($i)) {
                $columns[$i] = $this->quoteColumn($column);
            } else if (is_string($i)) {
                $columns[$i] = $this->quoteColumn($column) . " AS " . $this->quoteColumn($i);
            }
        }
        return $select . $this->separator . implode(',', $columns);
    }

    public function buildFrom($tables)
    {
        if (empty($tables)) {
            return null;
        }
        if (is_string($tables)) {
            $tables = [$tables];
        }

        foreach ($tables as $i => $table) {
            $tables[$i] = $this->quoteTable($table);
        }
        return 'FROM ' . implode(', ', $tables);
    }

    /**
     * @param array $joins
     * @return string $join_sql
     */
    public function buildJoin($joins)
    {
        if (empty($joins)) {
            return null;
        }
        foreach ($joins as $i => $join) {
            // 0:join type, 1:join table, 2:on-condition (optional)
            list($joinType, $table, $on) = $join;
            $table = $this->quoteTable($table);
            $table_array = explode($this->separator, $table);
            $alias = array_pop($table_array); // 表别名或表名
            $joins[$i] = "$joinType $table ";
            if (isset($on)) {
                $joinStyle = '';
                foreach ($on as $j => $keyRight) {
                    if (is_int($j)) {
                        $joinStyle .= $joinStyle == '|ANSI_USING' ? '' : '|ANSI_USING';
                        $on[$j] = $this->quoteColumn($keyRight);
                    } else if (is_string($j)) {
                        $joinStyle .= $joinStyle == '|ANSI_ON' ? '' : '|ANSI_ON';
                        $on[$j] = "{$alias}." . $this->quoteColumn($j) . "=" . $this->quoteColumn($keyRight);
                    }
                }
                $joinStyle = ltrim($joinStyle, '|');
                if ($joinStyle == 'ANSI_USING') {
                    $joins[$i] .= " USING(" . implode(',', $on) . ")";
                } else if ($joinStyle == 'ANSI_ON') {
                    $joins[$i] .= " ON " . implode(',', $on);
                } else {
                    throw new \RuntimeException('join style error', ErrorCode::ERR_DB_CODE);
                }
            }
        }
        return implode($this->separator, $joins);
    }

    /**
     * @param $condition
     * @param $params
     * @return string
     */
    public function buildWhere($condition, &$params)
    {
        if (empty($condition)) {
            return null;
        }
        $where_sql = $this->buildCondition($condition, $params);
        return $where_sql ? " WHERE {$where_sql}" : null;
    }

    public function buildGroupBy($columns)
    {
        if (empty($columns)) {
            return null;
        }
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->quoteColumn($column);
        }
        return "GROUP BY " . implode(', ', $columns);
    }

    public function buildHaving($condition, &$params)
    {
        if (empty($condition)) {
            return null;
        }
        $having_sql = $this->buildCondition($condition, $params);
        return $having_sql ? " HAVING {$having_sql}" : null;
    }

    /**
     * @param $columns
     * @return string
     */
    public function buildOrderBy($columns)
    {
        if (empty($columns)) {
            return null;
        }
        foreach ($columns as $i => $column) {
            if (!in_array($column, ['ASC', 'DESC'])) {
                throw new \RuntimeException('order by type error', ErrorCode::ERR_DB_CODE);
            }
            $columns[$i] = $this->quoteColumn($i) . $this->separator . $column;
        }
        return " ORDER BY " . implode(',', $columns);
    }

    private function buildCondition($condition, &$params)
    {
        if (empty($condition)) {
            return null;
        }
        foreach ($condition as $i => $unit) {
            list($operator, $key, $value) = $unit;
            if (empty($value)) {
                unset($condition[$i]);
                continue;
            }
            $parentOperator = is_int($i) ? "AND " : $i . $this->separator;
            if ($operator === 'in') {
                $params = array_merge($params, $value);
                $value = array_map(function ($v) {
                    return '?';
                }, $value);
                $condition[$i] = $parentOperator . $this->quoteColumn($key) . " IN(" . implode(',', $value) . ")";
                continue;
            }
            $params[] = $value;
            $condition[$i] = $parentOperator . $this->quoteColumn($key) . "{$operator}?";
        }
        return " 1=1 " . implode($this->separator, $condition);
    }

    public function buildLimit($limit, $offset)
    {
        $sql = '';
        if (ctype_digit((string)$limit)) {
            $sql = 'LIMIT ' . $limit;
        }
        if (ctype_digit((string)$offset) && (string)$offset !== '0') {
            $sql .= ' OFFSET ' . $offset;
        }
        return $sql ? $sql : null;
    }

    /**
     * @param $unions
     * @param $params
     * @return string
     */
    public function buildUnion($unions, &$params)
    {
        if (empty($unions)) {
            return null;
        }
        $result = '';
        foreach ($unions as $i => $union) {
            /**
             * @var KsMysql $query
             */
            $query = $union['query'];
            if ($query instanceof KsMysql) {
                list($unions[$i]['query'], $params) = $this->build($query, $params);
            }
            $result .= 'UNION ' . ($union['all'] ? 'ALL ' : '') . '( ' . $unions[$i]['query'] . ' ) ';
        }
        return trim($result);
    }

    public function insert($table, $columns)
    {
        $params = [];
        foreach ($columns as $key => $value) {
            $params[] = $value;
            unset($columns[$key]);
            $key = $this->quoteColumn($key);
            $columns[$key] = '?';
        }
        $this->formatParams($params);
        $sql = "INSERT INTO " . $this->quoteTable($table) . " (" . implode(',', array_keys($columns)) . ") VALUES (" . implode(',', $columns) . ")";
        return [$sql, $params];
    }

    public function update($table, $columns, $condition)
    {
        $params = [];
        foreach ($columns as $key => $value) {
            $params[] = $value;
            $columns[$key] = $this->quoteColumn($key) . "=?";
        }
        $where_sql = $this->buildWhere($condition, $params);
        $this->formatParams($params);
        $sql = "UPDATE " . $this->quoteTable($table) . " SET " . implode(',', $columns) . $where_sql;
        return [$sql, $params];
    }

    public function delete($table, $condition)
    {
        $params = [];
        $where_sql = $this->buildWhere($condition, $params);
        $this->formatParams($params);
        $sql = "DELETE FROM " . $this->quoteTable($table) . $where_sql;
        return [$sql, $params];
    }

    public function upsert($table, $insertColumns, $updateColumns)
    {
        $params = [];
        foreach ($insertColumns as $key => $value) {
            $params[] = $value;
            unset($insertColumns[$key]);
            $key = $this->quoteColumn($key);
            $insertColumns[$key] = '?';
        }
        foreach ($updateColumns as $key => $value) {
            $params[] = $value;
            $updateColumns[$key] = $this->quoteColumn($key) . "=?";
        }
        $this->formatParams($params);
        $sql = "INSERT INTO " . $this->quoteTable($table) . " (" . implode(',', array_keys($insertColumns)) . ") VALUES (" . implode(',', $insertColumns) . ") ON DUPLICATE KEY UPDATE " . implode(',', $updateColumns) . ";";
        return [$sql, $params];
    }

    public function batchInsert($table, $columns, $rows)
    {
        $params = [];
        foreach ($rows as $i => $row) {
            foreach ($row as $j => $unit) {
                $params[] = $unit;
                $row[$j] = '?';
            }
            $rows[$i] = "(" . implode(',', $row) . ")";
        }
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->quoteColumn($column);
        }
        $sql = "INSERT INTO " . $this->quoteTable($table) . " (" . implode(',', $columns) . ") VALUES " . implode(',', $rows) . ";";
        $this->formatParams($params);
        return [$sql, $params];
    }

    // rows = ['id'=>1,'name'=>'a'], updateFields = ['name']
    /*
     * UPDATE ad_channel_list
     *  SET ad_channel_name = CASE
     *      WHEN app_id='100000023' AND ad_channel='60049' THEN '未知60049'
     *      WHEN app_id='100000023' AND ad_channel='99999' THEN '未知99999'
     *      WHEN app_id='100000023' AND ad_channel='20009' THEN '未知20009'
     *  END,
     *  ad_channel_type = CASE
     *      WHEN app_id='100000023' AND ad_channel='60049' THEN 1
     *      WHEN app_id='100000023' AND ad_channel='99999' THEN 1
     *      WHEN app_id='100000023' AND ad_channel='20009' THEN 1
     *  END
     * WHERE ad_channel IN ('60049','99999','20009') AND app_id='100000023'
     */
    public function batchUpdate($table, $updateFields, $updateColumns)
    {
        $params = [];
        $tmpFields = $updateFields;
        foreach ($tmpFields as $i => $field) {
            $tmpColumns = $updateColumns;
            foreach ($tmpColumns as $j => $column) {
                $field_value = '';
                foreach ($column as $key => $unit) {
                    if ($key == $field) {
                        $field_value = $unit;
                    }
                    if (in_array($key, $updateFields)) {
                        unset($column[$key]);
                        continue;
                    }
                    $params[] = $unit;
                    $column[$key] = "{$key}=? ";
                }
                $params[] = $field_value;
                $tmpColumns[$j] = "WHEN " . implode("AND ", $column) . "THEN ?";
            }
            $tmpFields[$i] = " {$field} = CASE " . implode($this->separator, $tmpColumns) . " END";
        }
        $whereFields = array_diff(array_keys($updateColumns[0]), $updateFields);
        $condition = [];
        foreach ($whereFields as $i => $field) { // WHERE条件
            $condition[] = ['in', $field, ArrayUtils::columnToArray($field, $updateColumns)];
        }
        $whereParams = [];
        $where_sql = $this->buildWhere($condition, $whereParams);
        $params = array_merge($params, $whereParams);
        $sql = "UPDATE " . $this->quoteTable($table) . ' SET ' . implode(',', $tmpFields) . $where_sql;
        $this->formatParams($params);
        return [$sql, $params];
    }

    public function batchUpsert($table, $insertColumns, $updateFields)
    {
        $params = [];
        $insertKeys = [];
        foreach ($insertColumns as $i => $columns) {
            foreach ($columns as $key => $value) {
                $params[] = $value;
                unset($columns[$key]);
                $key = $this->quoteColumn($key);
                $columns[$key] = '?';
            }
            $insertKeys = array_keys($columns);
            $insertColumns[$i] = "(" . implode(',', $columns) . ")";
        }
        foreach ($updateFields as $i => $field) {
            $updateFields[$i] = $this->quoteColumn($field) . "=VALUES(" . $this->quoteColumn($field) . ")";
        }
        $sql = "INSERT INTO " . $this->quoteTable($table) . " (" . implode(',', $insertKeys) . ") VALUES " . implode(',', $insertColumns) . " ON DUPLICATE KEY UPDATE " . implode(',', $updateFields) . ";";
        $this->formatParams($params);
        return [$sql, $params];
    }


    /**
     * ---------------
     * 通用方法
     * ---------------
     */

    public function quoteColumn($name)
    {
        if (strpos($name, '(') !== false) {
            return $name;
        }
        if (count($unit = explode('.', $name)) > 1) {
            // 0:tableName 1:field
            $name = $this->quoteTable($unit[0]) . ($unit[1] == '*' ? ".{$unit[1]}" : ".`{$unit[1]}`");
        } else {
            $name = $name == '*' ? $name : "`{$name}`";
        }
        return $name;
    }

    public function quoteTable($name)
    {
        if (strpos($name, '(') !== false) {
            return $name;
        }
        $name = trim($name);
        if (count($unit = explode($this->separator, $name)) > 1) {
            // 0:tableName 1:alias
            $name = "`{$unit[0]}` `{$unit[1]}`";
        } else {
            $name = "`{$name}`";
        }
        return $name;
    }

    public function formatParams(&$params)
    {
        $tmp = [];
        foreach ($params as $i => $unit) {
            $tmp[++$i] = $unit;
        }
        return $params = $tmp;
    }
}