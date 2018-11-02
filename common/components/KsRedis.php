<?php
/**
 * User: donallin
 */

namespace common\components;

use Redis;

class KsRedis
{
    public $redisConfig = [];

    protected $host = 'localhost';
    protected $port = 6379;
    protected $password;
    protected $dbId = 0;
    protected $timeout = 30;
    protected $persist = false; //是否长连接,未使用
    protected $k;
    /**
     * @var \Redis
     */
    public $oRedis;
    static private $_instance = null;

    const DEFAULT_DB_TYPE = 'main';

    /**
     * 配置文件包含如下参数
     * [
     *  'host' => 'ip',
     *  'port'=> '端口'，
     *  'password' => '密码',
     *  'timeout' => '连接超时时间',
     *  'dbId' => '数据库ID',
     *  'errMode' => '错误处理模式'
     * ]
     * @param array $config
     * KsRedis constructor.
     */
    public function __construct(array $config = [])
    {
        $this->redisConfig = $config;
        foreach ($config as $key => $row) { // 对象成员赋值
            $this->$key = $row;
        }
        $this->oRedis = $this->connect();
    }

    /**
     * @param $config
     * @return KsRedis
     */
    public static function getInstance($config)
    {
        if (!is_array($config)) {
            // TODO:非组件配置获取
            $config = [];
        }
        if (!class_exists('Redis')) { //强制使用
            throw new \RuntimeException('This Lib Requires The Redis Extention!');
        }
        if (empty($config)) {
            throw new \RuntimeException('redis config no exist!');
        }
        $pid = intval(getmypid());
        $k = md5($config['host'] . $config['port'] . $config['dbId'] . $pid);

        if (empty(self::$_instance[$k])) {
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
        }
        return self::$_instance[$k];
    }

    /**
     * @param bool $force
     * @return \Redis
     */
    private function connect($force = false)
    {
        if ($this->oRedis !== null && $force == false) {
            return $this->oRedis;
        }
        $this->oRedis = new \Redis();
        $this->oRedis->connect($this->host, $this->port, $this->timeout);
        if (!is_null($this->password)) {
            $this->oRedis->auth($this->password);
        }
        if (!is_null($this->dbId)) {
            $this->oRedis->select($this->dbId);
        }
        return $this->oRedis;
    }

    /**
     * @return \Redis
     */
    public function getRedis()
    {
        $this->connect();
        return $this->oRedis;
    }

    /**
     * php redis extension
     * @param string $func_name
     * @param array $params
     * @return mixed
     */
    public function __call($func_name, $params)
    {
        return call_user_func_array([$this->oRedis, $func_name], $params);
    }

    public function acquireLock($key, $expire_time = 5)
    {
        $lock_value = time() + $expire_time;
        $redis = $this->oRedis;

        $lock_key = RKey::LOCK . $key; // 操作锁

        $lock = $redis->setnx($lock_key, $lock_value); // set if not exists; 1-成功，0-失败
        // 1、锁成功  2、锁过期 且 设置锁新的值成功
        if (!empty($lock) || ($redis->get($lock_key) < time() && $redis->getSet($lock_key, $lock_value) < time())) {
            $redis->expire($lock_key, $expire_time);
            return true;
        }
        return false;
    }

    public function releaseLock($key)
    {
        $redis = $this->oRedis;

        $lock_key = RKey::LOCK . $key; // 操作锁

        if ($redis->ttl($lock_key)) {
            $redis->del($lock_key);
        }
        return true;
    }

    /**
     * @param $rank_key
     * @param $id
     * @param $score
     * @param int $max
     * @return int
     */
    public function setRankLimit($rank_key, $id, $score, $max = 1000)
    {
        $redis = $this->oRedis;

        $pipe = $redis->multi(\Redis::PIPELINE);
        $pipe->zAdd($rank_key, $score, $id);
        $pipe->zCard($rank_key);
        $ret = $pipe->exec();
        $count = intval($ret[1]);
        if ($ret[1] > $max) {
            $diff = $ret[1] - $max;
            $redis->zRemRangeByRank($rank_key, 0, $diff - 1);
            $count = $max;
        }
        return $count;
    }

}