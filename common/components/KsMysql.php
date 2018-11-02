<?php
/**
 * User: donallin
 */

namespace common\components;

use PDO;
use PDOException;

/**
 * Class KsMysql
 * @package common\components
 * @see SqlBuilder
 */
class KsMysql
{
    // 数据库配置
    public $dbConfig = [];

    // sql语句
    public $sqlQuery = [
        'select' => [],
        'selectOption' => '',
        'distinct' => false,
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'orderBy' => [],
        'having' => [],
        'limit' => '',
        'offset' => 0,
        'indexBy' => null,
        'union' => [],
        'useMaster' => 1
    ];
    // sql参数
    public $params = [];

    private $_sql;
    /**
     * @var PDO
     */
    private $_pdo;
    private static $_instance = [];

    protected $driver; /* 驱动类型 */
    protected $dbName; /* 当前数据库名 */
    protected $username = null; /* 用户名 */
    protected $dsn; /* 驱动dsn */
    protected $k; /* 当前数据库连接标识符 */
    protected $password = null; /* 密码 */
    protected $host = 'localhost'; /* 主机名 */
    protected $port = '3306'; /* 端口号 */
    protected $charset;
    /* PDO链接属性数组 */
    protected $attr = [
        /* 这个超时参数，实际上mysql服务器上的配置为准的 */
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false /* 是否使用长链接 */
    ];
    protected $commands = []; // 初始化PDO时执行
    private $_stmt = null;

    const DEFAULT_DB_TYPE = 'main';
    /*
     * mysql常用错误码定义
     * 驱动错误码保存在 errInfo[1]中
    */
    const ERR_DRIVER_TABLE = 1146; /* 表不存存 */
    const ERR_DRIVER_CONN = 2006; /* 连接已经断开 */
    const ERR_DRIVER_INTERRUP = 70100; /* 查询执行被中断 */

    /**
     * 构造方法
     * config 应该包含如下配置：
     * [
     *  'dsn' => '数据库驱动',
     *  'username' => '用户名',
     *  'password' => '用户密码',
     *  'slaves' => '从库列表，数组',
     *  'cache' => '字段缓存使用的组件名',
     *  'charset' => '字符集',
     *  'attr' => '属性',
     *  'prefix' => '表前缀'
     * ]
     * @param array $config 配置文件
     */
    public function __construct(array $config = [])
    {
        $this->dbConfig = $config;
        foreach ($this->dbConfig as $key => $row) {
            $this->$key = $row;
        }
    }

    public function connect($force = false)
    {
        if ($this->_pdo !== null && $force == false) {
            return $this->_pdo;
        }
        $this->_pdo = null;
        $this->setParamByDsn($this->dsn);
        if ($this->charset) {
            $this->commands[] = "SET NAMES '{$this->charset}'";
        }
        try {
            $this->_pdo = new PDO($this->dsn, $this->username, $this->password, $this->attr);
            foreach ($this->commands as $value) {
                $this->_pdo->exec($value);
            }
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), $e->getCode());
        }
        return $this->_pdo;
    }

    public function setParamByDsn($dsn)
    {
        list($this->driver, $tmp) = explode(':', $dsn);
        foreach (explode(';', $tmp) as $unit) { // 设置基本信息
            list($key, $value) = explode('=', $unit);
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            switch (strtolower(trim($key))) {
                case 'host' :
                    $this->host = KsUtils::defaultValue($value, $this->host, 'string');
                    break;
                case 'port' :
                    $this->port = KsUtils::defaultValue($value, $this->port, 'int');
                    break;
                case 'dbname' :
                    $this->dbName = $value;
                    break;
            }
        }
        if ($this->driver == 'mysql') {
            // Make MySQL using standard quoted identifier
            $this->commands[] = 'SET SQL_MODE=ANSI_QUOTES';
        }
    }

    /**
     * @param SqlBuilder $builder
     * @return $this
     */
    public function prepare($builder)
    {
        return $this;
    }

    /**
     * @param $config
     * @return KsMysql
     */
    public static function getInstance($config)
    {
        if (!is_array($config)) {
            // TODO:非组件配置获取
            $config = [];
        }
        if (empty($config)) {
            throw new \RuntimeException('db config no exist!');
        }
        $pid = intval(getmypid());
        $k = md5($config['dsn'] . $config['username'] . $config['password'] . $pid);

        if (empty(self::$_instance[$k])) {
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
        }
        return self::$_instance[$k];
    }

    /**
     * @param array|string $columns
     * // SELECT `id`,`name`,`user`.`user_id`,`user`.`user_id` AS `user_id`,SUM(money) AS `sum` ...
     * For example, `['id', 'name', 'user.user_id', 'user_id' => 'user.user_id', 'sum' => SUM(money)]`
     * @param string $option
     * // SELECT SQL_CALC_FOUND_ROWS * FROM ...
     * For example, 'SQL_CALC_FOUND_ROWS'
     * @link https://dev.mysql.com/doc/refman/8.0/en/information-functions.html
     * @return $this
     */
    public function select($columns, $option = null)
    {
        $this->sqlQuery['select'] = is_array($columns) ? array_unique($columns) : $columns; // 获取唯一的field
        $this->sqlQuery['selectOption'] = $option;
        return $this;
    }

    /**
     * @param bool $value
     * // SELECT DISTINCT * ...
     * For example, boolean `true`
     * @return $this
     */
    public function distinct($value = true)
    {
        $this->sqlQuery['distinct'] = $value;
        return $this;
    }

    /**
     * @param string|array $tables
     * // SELECT * FROM  `user` `u`, `profile`;
     * For example, ['user u', 'profile']
     * @return $this
     */
    public function from($tables)
    {
        $this->sqlQuery['from'] = $tables;
        return $this;
    }

    /**
     * @param string $type such as INNER JOIN, LEFT JOIN.
     * @param string $table
     * // JOIN `user` `u`
     * For example, `user u`
     * @param $on
     * // USING(`user_id`,`age`)
     * For example, ['user_id','age']
     * // ON `b`.`uid`=`a`.`user_id` AND `b`.`age`=`a`.`age`,b为join的表$table,且不可同时出现on和using两种类型
     * For example, ['uid' => 'a.user_id','age' => 'a.age']
     * @return $this
     */
    public function join($type, $table, $on)
    {
        $this->sqlQuery['join'][] = [$type, $table, $on];
        return $this;
    }

    /**
     * @param $columns
     * // SELECT `id`,`name`,`user`.`user_id`,`user`.`user_id` AS `user_id`,SUM(money) AS `sum` ...
     * For example, `['id', 'name']`
     * @return $this
     */
    public function addSelect($columns)
    {
        $this->sqlQuery['select'] = array_unique(array_merge($this->sqlQuery['select'], $columns));
        return $this;
    }

    /**
     * @param array $where
     * // WHERE `abc`=123 AND `table`.`d` IN(1,2,3)
     * For example, [['=','abc',123],['in','table.d',[1,2,3]]]
     * @return $this
     */
    public function where($where = [])
    {
        $this->sqlQuery['where'] = array_merge($this->sqlQuery['where'], $where);
        return $this;
    }

    /**
     * @param array $columns
     * // GROUP BY `a`,`table`.`b`
     * For example, ['a','table.b']
     * @return $this
     */
    public function groupBy($columns)
    {
        $this->sqlQuery['groupBy'] = $columns;
        return $this;
    }

    /**
     * @param array $condition
     * // HAVING SUM(`price`)>123 AND COUNT(`num`) IN(1,2,3)
     * For example, [['>',SUM(`price`),123],['in',COUNT(`num`),[1,2,3]]]
     * @return $this
     */
    public function having($condition)
    {
        $this->sqlQuery['having'] = $condition;
        return $this;
    }

    /**
     * @param array $columns
     * // ORDER BY `a` ASC,`b` DESC
     * For example, ['a'=>'ASC','b'=>'DESC']
     * @return $this
     */
    public function orderBy($columns)
    {
        $this->sqlQuery['orderBy'] = $columns;
        return $this;
    }

    /**
     * @param int $limit
     * // LIMIT 2
     * For example, 2
     * @return $this
     */
    public function limit($limit)
    {
        $this->sqlQuery['limit'] = $limit;
        return $this;
    }

    /**
     * @param int $offset
     * // OFFSET 1
     * For example, 1
     * @return $this
     */
    public function offset($offset)
    {
        $this->sqlQuery['offset'] = $offset;
        return $this;
    }

    /**
     * TODO:未可使用
     * @param string|KsMysql $sql
     * @param bool $all
     * ```sql
     *  SELECT E_Name FROM Employees_China
     *  UNION ALL
     *  SELECT E_Name FROM Employees_USA
     * ```
     * @return $this
     */
    public function union($sql, $all = false)
    {
        $this->sqlQuery['union'][] = ['query' => $sql, 'all' => $all];
        return $this;
    }
    // TODO 添加clone方法,和union联合使用

    /**
     * @return bool|\PDOStatement
     */
    public function query()
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->build($this);
        $this->clearSql();
        $stmt = $this->_execute($sql, $params);
        return $stmt;
    }

    /**
     * Executes the SQL statement and returns the first row of the result.
     * @param int $fetchMode
     * @return array|false the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function one($fetchMode = PDO::FETCH_ASSOC)
    {
        $this->limit(1);
        $stmt = $this->query();
        return $stmt->fetch($fetchMode);
    }

    /**
     * Executes the SQL statement and returns ALL rows at once.
     * @param int $fetchMode
     * @return array all rows of the query result. Each array element is an array representing a row of data.
     * An empty array is returned if the query results in nothing.
     */
    public function all($fetchMode = PDO::FETCH_ASSOC)
    {
        $stmt = $this->query();
        return $stmt->fetchAll($fetchMode);
    }

    /**
     * Executes the SQL statement.
     * This method should only be used for executing non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     * @return int number of rows affected by the execution.
     */
    public function execute()
    {
        $stmt = $this->_execute($this->_sql, $this->params);
        $this->clearSql();
        return $stmt->rowCount();
    }

    /**
     * Creates an INSERT sql.
     *
     * For example,
     *
     * ```php
     * (new KsMysql())->insert('user', [
     *     'name' => 'Sam',
     *     'age' => 30,
     * ])->execute();
     * ```
     * @param string $table
     * @param array $columns
     * @return KsMysql
     */
    public function insert($table, $columns)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->insert($table, $columns);
        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a batch INSERT sql.
     *
     * For example,
     *
     * ```php
     * (new KsMysql())->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ])->execute();
     * @param string $table
     * @param array $columns
     * @param array $rows
     * @return KsMysql
     */
    public function batchInsert($table, $columns, $rows)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->batchInsert($table, $columns, $rows);
        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates an UPDATE sql.
     *
     * For example,
     *
     * ```php
     * (new KsMysql())->update('user', ['status' => 1], [
     *      ['>', 'age', 3],
     *      'OR'=>['=', 'age', 3]
     * ])->execute();
     * ```
     * @param string $table
     * @param array $columns
     * @param array $condition
     * @return KsMysql
     */
    public function update($table, $columns, $condition)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->update($table, $columns, $condition);
        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates an BATCH UPDATE sql.
     *
     * For example,
     *
     * ```php
     * (new KsMysql())->batchUpdate('pages', ['name'], [
     *     ['id' => '1001','name' => 'a'],
     *     ['id' => '1002','name' => 'b'],
     * ]);
     * ```
     * @param $table
     * @param array $updateFields 更新的字段
     * @param array $updateColumns 在updateFields以外都是判定条件，类似id
     * @return KsMysql
     */
    public function batchUpdate($table, $updateFields, $updateColumns)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->batchUpdate($table, $updateFields, $updateColumns);
        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a DELETE SQL.
     *
     * For example,
     *
     * ```php
     * $sql = (new KsMysql())->delete('user', [
     *          ['>', 'age', 3]
     *      ]);
     * ```
     * @param string $table
     * @param array $condition
     * @return KsMysql
     */
    public function delete($table, $condition)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->delete($table, $condition);
        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a sql to insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or update them if they do.
     *
     * For example,
     *
     * ```php
     * (new KsMysql())->upsert('pages', [
     *     'name' => 'Front page',
     *     'url' => 'http://example.com/', // url is unique
     *     'visits' => 0,
     * ], [
     *     'visits' => 1,
     * ]);
     * ```
     * @param string $table
     * @param array $insertColumns
     * @param array $updateColumns
     * @return KsMysql
     */
    public function upsert($table, $insertColumns, $updateColumns)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->upsert($table, $insertColumns, $updateColumns);
        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a sql to batch insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or batch update them if they do.
     *
     * For example,
     *
     * ```php
     * (new KsMysql())->batchUpsert('pages', [
     *     ['name' => 'Front page','url' => 'http://a-example.com/', 'visits' => 0], // url is unique
     *     ['name' => 'Front page','url' => 'http://b-example.com/', 'visits' => 2],
     * ], ['visits','name']);
     * ```
     * @param string $table
     * @param array $insertColumns
     * @param array $updateFields
     * @return KsMysql
     */
    public function batchUpsert($table, $insertColumns, $updateFields)
    {
        $builder = SqlBuilder::getInstance();
        list($sql, $params) = $builder->batchUpsert($table, $insertColumns, $updateFields);
        return $this->setSql($sql)->bindValues($params);
    }

    public function id()
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * Specifies the SQL statement to be executed.
     * @param $sql
     * @return KsMysql $this
     */
    public function setSql($sql)
    {
        if ($sql !== $this->_sql) {
            $this->clearSql();
            $this->_sql = $sql; // TODO:sql可用quote
        }
        return $this;
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * @param $values
     * @return KsMysql $this
     */
    public function bindValues($values)
    {
        if (!empty($values)) {
            $this->params = $values;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getSql()
    {
        $sql = $this->_sql;
        $params = $this->params;
        if (empty($sql)) { // query时
            $builder = SqlBuilder::getInstance();
            list($sql, $params) = $builder->build($this);
            $this->clearSql();
        }
        return [$sql, $params];
    }

    /**
     * @return string
     */
    public function getRawSql()
    {
        list($sql, $params) = $this->getSql();
        if (!isset($params[1])) {
            return strtr($sql, $params);
        }
        $rawSql = '';
        foreach (explode('?', $sql) as $i => $part) {
            $rawSql .= (isset($params[$i]) ? $this->quoteValue($params[$i]) : '') . $part;
        }
        return $rawSql;
    }

    public function quoteValue($value)
    {
        switch (gettype($value)) {
            case 'string':
                $value = "'{$value}'";
                break;
            default:
        }
        return $value;
    }

    public function clearSql()
    {
        $this->sqlQuery = [
            'select' => [],
            'selectOption' => '',
            'distinct' => false,
            'from' => [],
            'join' => [],
            'where' => [],
            'groupBy' => [],
            'orderBy' => [],
            'having' => [],
            'limit' => '',
            'offset' => 0,
            'indexBy' => null,
            'union' => [],
            'useMaster' => 1
        ];

        $this->params = [];
        $this->_sql = null;
        $this->_stmt = null;
    }

    private function _execute($sql, $params = [], $useMaster = 0)
    {
        // TODO:主从设置
        // 默认尝试两次,重连
        for ($i = 0; $i < 2; $i++) {
            try {
                $pdo = $this->connect($i);
                $stmt = $pdo->prepare($sql);
                foreach ($params as $i => &$unit) {
                    $param_type = self::_paramType($unit); // TODO:要求数据输入时的准确
                    $stmt->bindParam($i, $unit, $param_type);
                }
                $stmt->execute();
                $this->_stmt = $stmt;

                /* 此处用于静默错误模式下的断线重连 */
                $errorInfo = $stmt->errorInfo();
                if ($errorInfo[0] != '00000') {
                    $e = new PDOException($errorInfo[2]);
                    $e->errorInfo = $stmt->errorInfo();
                    throw $e;
                }
                return $stmt;
            } catch (PDOException $e) {
                /* 事务状态下，不可以使用断线重连。应该直接报错，rollback事务。 */
                if ($i == 0 && $e->errorInfo[1] == self::ERR_DRIVER_CONN) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Determines the PDO type for the given PHP data value.
     * @param mixed $data the data whose PDO type is to be determined
     * @return int the PDO type
     * @see http://www.php.net/manual/en/pdo.constants.php
     */
    private function _paramType($data)
    {
        static $typeMap = [
            // php type => PDO type
            'boolean' => \PDO::PARAM_INT, // PARAM_BOOL is not supported by CUBRID PDO
            'integer' => \PDO::PARAM_INT,
            'string' => \PDO::PARAM_STR,
            'resource' => \PDO::PARAM_LOB,
            'NULL' => \PDO::PARAM_NULL,
        ];
        $type = gettype($data);

        return isset($typeMap[$type]) ? $typeMap[$type] : \PDO::PARAM_STR;
    }
}