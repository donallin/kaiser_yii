<?php
/**
 * User: donallin
 */

namespace backend\modules\test\controllers;

use common\components\KsComponent;
use common\components\KsUtils;
use Yii;
use common\core\CoreController;

class TestController extends CoreController
{
    public function actionIndex()
    {
    }

    public function actionRedis()
    {
        $redis = KsComponent::redis();
        $redis->set('testAbc', 222);
        $ret = $redis->get('testAbc');
        var_dump($ret);
    }

    public function actionQuery()
    {
        $db = KsComponent::db();
        $sql = $db
            ->from('ks_user u')
            ->join('LEFT JOIN', 'ks_user_wechat wechat', ['user_id'])
            ->addSelect(['SUM(sex) AS sum'])
            ->orderBy(['u.user_id' => 'ASC'])
            ->groupBy(['u.sex'])
            ->having([
                ['>', 'sum', 2]
            ])
            ->getRawSql();
        var_dump($sql);
//        exit;
        $ret = $db
            ->from('ks_user u')
            ->join('LEFT JOIN', 'ks_user_wechat wechat', ['user_id'])
            ->addSelect(['SUM(sex) AS sum'])
            ->orderBy(['u.user_id' => 'ASC'])
            ->groupBy(['u.sex'])
            ->having([
                ['>', 'sum', 2]
            ])
            ->one();
        var_dump($ret);
    }

    public function actionInsert()
    {
        $db = KsComponent::db();
        $sql = $db
            ->insert('ks_user', [
                'user_id' => 6,
                'nickname' => 'test',
                'sex' => 2
            ])->getRawSql();
        var_dump("sql:" . $sql);
        $ret = $db
            ->insert('ks_user', [
                'user_id' => 6,
                'nickname' => 'test',
                'sex' => 2
            ])->execute();
        var_dump($ret);
        var_dump($db->id());
    }

    public function actionUpdate()
    {
        $db = KsComponent::db();
        $sql = $db
            ->update('ks_user', ['nickname' => 'test_update'], [
                ['=', 'user_id', 4]
            ])->getRawSql();
        var_dump("sql:" . $sql);
        $ret = $db
            ->update('ks_user', ['nickname' => 'test_update'], [
                ['=', 'user_id', 4]
            ])->execute();
        var_dump($ret);

    }

    public function actionBatchInsert()
    {
        $db = KsComponent::db();
        $sql = $db
            ->batchInsert('ks_user', ['user_id', 'nickname', 'sex'], [
                [7, 'peter', 1],
                [8, 'anna', 2]
            ])->getRawSql();
        var_dump($sql);
//        exit;
        $ret = $db
            ->batchInsert('ks_user', ['user_id', 'nickname', 'sex'], [
                [7, 'peter', 1],
                [8, 'anna', 2]
            ])->execute();
        var_dump($ret);
    }

    public function actionUpsert()
    {
        $db = KsComponent::db();
        $sql = $db
            ->upsert('ks_user', [
                'user_id' => 9,
                'nickname' => 'ken',
                'sex' => 1
            ], [
                'sex' => 2
            ])->getRawSql();
        var_dump($sql);
//        exit;
        $ret = $db
            ->upsert('ks_user', [
                'user_id' => 9,
                'nickname' => 'ken',
                'sex' => 1
            ], [
                'sex' => 2
            ])->execute();
        var_dump($ret);
    }

    public function actionBatchUpsert()
    {
        $db = KsComponent::db();
        $sql = $db
            ->batchUpsert('ks_user', [
                ['user_id' => 8, 'nickname' => 'anna', 'sex' => 1],
                ['user_id' => 10, 'nickname' => 'hello', 'sex' => 2]
            ], ['sex'])->getRawSql();
        var_dump($sql);
//        exit;
        $ret = $db
            ->batchUpsert('ks_user', [
                ['user_id' => 8, 'nickname' => 'anna', 'sex' => 1],
                ['user_id' => 10, 'nickname' => 'hello', 'sex' => 2]
            ], ['sex'])->execute();
        var_dump($ret);
    }
}
