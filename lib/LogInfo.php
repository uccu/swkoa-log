<?php

namespace Uccu\SwKoaLog;

use DateTime;

class LogInfo
{

    public $params = [];
    public $level;
    public $time;
    public $tag;
    public $workerId;
    public $importFile;

    public function __construct(string $level = Log::LEVEL_INFO, string $tag = 'master', int $workerId = 0)
    {
        $this->importFile = true;
        $date = new DateTime();
        $this->time = $date->format('Y-m-d H:i:s:u');
        $this->level = $level;
        $this->tag = $tag;
        $this->workerId = $workerId;
    }

    public function addParam(LogParam $param): LogInfo
    {
        array_push($this->params, $param);
        return $this;
    }
}
