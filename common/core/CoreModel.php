<?php
/**
 * User: donallin
 */

namespace common\core;

use common\components\KsComponent;
use common\components\KsMysql;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

class CoreModel
{
    public static function tableName()
    {
        return Inflector::camel2id(StringHelper::basename(get_called_class()), '_');
    }

    public static function getDb()
    {
        return KsComponent::db('main');
    }

    /**
     * @param array|string $fields
     *      `['id', 'name', 'user.user_id', 'user_id' => 'user.user_id', 'sum' => SUM(money)]`
     *      `'*'`
     * @param array $where
     *      `[
     *          ['=', 'abc', 123],
     *          ['in', 'table.d', [1,2,3]]
     *      ]`
     * @param array $group_by
     *      `['a','table.b']`
     * @param array $order_by
     *      `['a'=>'ASC','b'=>'DESC']`
     * @return array $ret
     *      all rows of the query result.
     *
     */
    public static function getList($fields = '*', $where = [], $group_by = [], $order_by = [])
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command->from($table_name)
            ->select($fields)
            ->where($where)
            ->groupBy($group_by)
            ->orderBy($order_by)
            ->all();
//        $sql = $command->from($table_name)
//            ->select($fields)
//            ->where($where)
//            ->groupBy($group_by)
//            ->orderBy($order_by)
//            ->getRawSql();
//        var_dump($sql);
//        exit;
        return $ret;
    }

    /**
     * @param array|string $fields
     *      `['id', 'name', 'user.user_id', 'user_id' => 'user.user_id', 'sum' => SUM(money)]`
     *      `'*'`
     * @param array $where
     *      `[
     *          ['=', 'abc', 123],
     *          ['in', 'table.d', [1,2,3]]
     *      ]`
     * @param array $group_by
     *      `['a','table.b']`
     * @param array $order_by
     *      `['a'=>'ASC','b'=>'DESC']`
     * @return array|false $ret
     *      the first row (in terms of an array) of the query result
     */
    public static function getOne($fields = '*', $where = [], $group_by = [], $order_by = [])
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command->from($table_name)
            ->select($fields)
            ->where($where)
            ->groupBy($group_by)
            ->orderBy($order_by)
            ->one();
//        $sql = $command->from($table_name)
//            ->select($fields)
//            ->where($where)
//            ->groupBy($group_by)
//            ->orderBy($order_by)
//            ->getRawSql();
//        var_dump($sql);exit;
        return $ret;
    }

    /**
     * @param array $update_columns
     *      `['status' => 1]`
     * @param array $where
     *      `[
     *          ['=', 'abc', 123],
     *          ['in', 'table.d', [1,2,3]]
     *      ]`
     * @return int $ret rowCount
     */
    public static function update($update_columns = [], $where = [])
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command
            ->update($table_name, $update_columns, $where)
            ->execute();
//        $sql = $command
//            ->update($table_name, $update_columns, $where)
//            ->getRawSql();
//        var_dump($sql);exit;
        return $ret;
    }

    /**
     * @param array $insert_columns
     *      `[
     *          'name' => 'Sam',
     *          'age' => 30,
     *      ]`
     * @return string insert_id
     */
    public static function insert($insert_columns = [])
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command
            ->insert($table_name, $insert_columns)
            ->execute();
//        $sql = $command
//            ->insert($table_name, $insert_columns)
//            ->getRawSql();
//        var_dump($sql);exit;
        return $command->id();
    }

    public static function updsert($insert_columns, $update_columns)
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command
            ->upsert($table_name, $insert_columns, $update_columns)
            ->execute();
        return $ret;
    }

    public static function delete($where = [])
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command
            ->delete($table_name, $where)
            ->execute();
        return $ret;
    }

    public static function batchInsert($columns, $rows)
    {
        /* @var $class_name CoreModel */
        $class_name = get_called_class();
        $table_name = $class_name::tableName();
        /* @var $command KsMysql */
        $command = $class_name::getDb();
        $ret = $command
            ->batchInsert($table_name, $columns, $rows)
            ->execute();
//        $sql = $command
//            ->batchInsert($table_name, $columns, $rows)
//            ->getRawSql();
//        var_dump($sql);exit;
        return $ret;
    }
}