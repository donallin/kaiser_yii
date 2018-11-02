<?php
/**
 * User: donallin
 */

namespace common\components;

use yii\base\Component;

class KsModel extends Component
{
    public $dbConfig;
    public $redisConfig;

    private $type;

    const DEFAULT_DB_TYPE = 'main';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->type = $this->dbConfig ? 'mysql' : ($this->redisConfig ? 'redis' : '');
    }

    /**
     * @param $dbType
     * @return KsMysql|KsRedis
     */
    public function getInstance($dbType = self::DEFAULT_DB_TYPE)
    {
        $instance = null;
        $config = $this->type == 'mysql' ? $this->dbConfig : $this->redisConfig;
        $config = KsUtils::defaultValue($dbType, [], 'array', $config);
        switch ($this->type) {
            case 'mysql':
                $instance = KsMysql::getInstance($config);
                break;
            case 'redis':
                $instance = KsRedis::getInstance($config);
                break;
            default:
                throw new \RuntimeException('KsModel config type do not exists!');
        }
        return $instance;
    }
}