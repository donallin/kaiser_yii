<?php

namespace common\components;

use common\components\lib\Json;
use yii\base\Component;

class KsLogger extends Component
{
    /**
     * @var bool 是否使用远程发送日志。必须配置remote_log 组件
     */
    public $enableRemote = false;
    /**
     * @var bool 日志是否写文件
     */
    public $enableFile = true;
    /**
     * 目录权限
     * @var int
     */
    protected $dirMode = 0755;
    /**
     * 文件权限
     * @var int
     */
    protected $fileMode = 0664;

    /**
     * 记录错误日志
     * @param $msg
     * @param string $file
     */
    public function error($msg, $file = '')
    {
        $file = self::getLogFile($file, $level = 'error');
        self::_log($file, $msg);
    }

    /**
     * 访问日志
     * @param $msg
     * @param string $file
     */
    public function access($msg, $file = '')
    {
        $file = self::getLogFile($file, $level = 'error');
        self::_log($file, $msg);
    }

    /**
     * 调试日志
     * @param $msg
     * @param string $file
     */
    public function debug($msg, $file = '')
    {
        $file = self::getLogFile($file, $level = 'error');
        self::_log($file, $msg);
    }

    /**
     * 向指定文件记录日志。
     * @param $msg
     * @param $fileName
     */
    public function log($fileName, $msg)
    {
        $file = dirname(__DIR__) . "/runtime/logs/" . $fileName;
        self::_log($file, $msg);
    }

    /**
     * 向指定文件记录日志。
     * @param $msg
     * @param string $file
     */
    public function info($msg, $file = '')
    {
        $file = self::getLogFile($file, $level = 'info');
        self::_log($file, $msg);
    }

    /**
     * 日志方法
     * @param $file
     * @param $msg
     */
    private function _log($file, $msg)
    {
        if (is_array($msg)) {
            $msg = Json::encode($msg);
        }
        $time = date('Y-m-d H:i:s');
        $zone = date_default_timezone_get();
        $msg = "[{$time} {$zone}] {$msg}";
        if ($this->enableFile) {
            $this->createFile($file);
            file_put_contents($file, "{$msg},line:" . __LINE__ . "\n", FILE_APPEND);
        }
        /**
         * 添加远程日志
         */
        /*if ($this->enableRemote && $this->remoteLog) {
            $mark = explode('_', basename($file, '.log'))[0];
            $this->remoteLog->log($mark, $msg);
        }*/
    }

    /**
     * 日志和文件的创建
     * @param $file
     */
    public function createFile($file)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, $this->dirMode, true);
        }
        if (!is_file($file)) {
            touch($file);
            @chmod($file, $this->fileMode);
        }
    }


    /**
     * 得到当前调试日志的文件路径
     * @param string $fileName
     * @param string $level
     * @return string
     */
    public function getLogFile($fileName = '', $level = 'info')
    {
        $fileName = $fileName ? "{$fileName}_" : $fileName;
        $file = dirname(__DIR__) . "/runtime/logs/" . date('Ymd') . "{$fileName}{$level}.log";//如果不传，则只是记录一些日常日志
        return $file;
    }
}