<?php
/**
 * User: donallin
 */

namespace common\components;

use Yii;

class KsComponent
{
    /**
     * 获取微信组件
     * @return WechatSdk
     */
    public static function wechatSdk()
    {
        return Yii::$app->wechatSdk;
    }

    /**
     * 获取SSO组件
     * @param string $name
     * @return SsoSdk
     */
    public static function ssoSdk($name = '')
    {
        $name = "{$name}SsoSdk";
        return Yii::$app->$name;
    }

    /**
     * @param string $name
     * @return KsRedis|\Redis
     */
    public static function redis($name = 'main')
    {
        /**
         * @var KsModel $ksRedis
         */
        $ksRedis = Yii::$app->redis;
        return $ksRedis->getInstance($name);
    }

    /**
     * @param string $name
     * @return KsMysql
     */
    public static function db($name = 'main')
    {
        /**
         * @var KsModel $ksMysql
         */
        $ksMysql = Yii::$app->db;
        return $ksMysql->getInstance($name);
    }

    /**
     * @return WordsFilter
     */
    public static function wordsFilter()
    {
        return Yii::$app->wordsFilter;
    }

    /**
     * @return KsLogger
     */
    public static function logger()
    {
        return Yii::$app->ksLogger;
    }
}