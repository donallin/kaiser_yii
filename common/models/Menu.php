<?php

namespace common\models;

use common\components\KsComponent;
use common\core\CoreModel;

/**
 * This is the model class for table "menu".
 */
class Menu extends CoreModel
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'menu';
    }

    /**
     * @return \common\components\KsMysql
     */
    public static function getDb()
    {
        return KsComponent::db('main');
    }
}
