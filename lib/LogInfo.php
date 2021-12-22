<?php

namespace Uccu\SwKoaLog;

use DateTime;

class LogInfo
{

    public $params = [];
    public $time;
    public $tag;
    public $workerId;
    public $level;

    /**
     * @var bool
     */
    public $importFile;

    public function __construct(string $tag = 'master', bool $importFile = true, int $workerId = 0)
    {
        $date = new DateTime();
        $this->time = $date->format('Y-m-d H:i:s:u');
        $this->tag = $tag;
        $this->importFile = $importFile;
        $this->workerId = $workerId;
    }

    public function addParam(LogParam $param): LogInfo
    {
        array_push($this->params, $param);
        return $this;
    }
}
