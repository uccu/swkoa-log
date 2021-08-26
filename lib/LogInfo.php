<?php

namespace Uccu\SwKoaLog;

use DateTime;

class LogInfo
{

    public $params = [];
    public $type;
    public $time;
    public $tag;
    public $workerId;
    public $importFile;

    public function __construct(string $type = 'info', string $tag = 'master', int $workerId = 0)
    {
        $this->importFile = true;
        $date = new DateTime();
        $this->time = $date->format('Y-m-d H:i:s:u');
        $this->type = $type;
        $this->tag = $tag;
        $this->workerId = $workerId;
    }

    public function addParam(LogParam $param): LogInfo
    {
        array_push($this->params, $param);
        return $this;
    }
}
