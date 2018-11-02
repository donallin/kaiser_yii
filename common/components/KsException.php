<?php

namespace common\components;

use Throwable;
use Exception;

class KsException extends Exception
{
    /**
     * @var mixed 数据
     */
    public $data;

    public function __construct($message = "", $code = 0, $data = [], Throwable $previous = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData()
    {
        return $this->data;
    }

}