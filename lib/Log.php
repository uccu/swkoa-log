<?php

namespace Uccu\SwKoaLog;

use Swoole\Process\Pool;

class Log
{

    const LEVEL_DEBUG   = 0;
    const LEVEL_TRACE   = 1;
    const LEVEL_INFO    = 2;
    const LEVEL_WARN    = 3;
    const LEVEL_ERROR   = 4;

    private $pool;
    public $workerId;
    private $importFile;


    public function __construct()
    {
        $this->importFile = true;
    }

    public static function setConfig(array $conf)
    {
        $log = Log::getInstance();
        foreach ($conf as $k => $c) {
            $log->$k = $c;
        }
    }

    public static function getInstance()
    {
        static $object;
        if (empty($object)) {
            $params = func_get_args();
            $object = new static(...$params);
        }
        return $object;
    }

    public static function _execFunc(Pool $pool, $workerId)
    {
        Log::setPool($pool, $workerId);
        Log::setConfig(['importFile' => false]);
        $process = $pool->getProcess();
        $socket = $process->exportSocket();

        $log = Log::getInstance();
        while (1) {
            $recv = $socket->recv(65535, 3600);
            if ($recv === "" || $recv === false) {
                continue;
            }
            $recv = json_decode($recv);
            $log->log($recv);
        }
    }

    protected function log($recv)
    {

        $stdStr = "";
        $fileStr = "";

        $time = $this->timeFormat($recv->time);
        $stdStr .= $time->stdStr;
        $fileStr .= $time->fileStr;

        $tag = $this->tagFormat($recv->tag);
        $stdStr .= $tag->stdStr;
        $fileStr .= $tag->fileStr;

        $workerId = $this->workerIdFormat($recv->workerId);
        $stdStr .= $workerId->stdStr;
        $fileStr .= $workerId->fileStr;

        $level = $this->typeFormat($recv->type);
        $stdStr .= $level->stdStr;
        $fileStr .= $level->fileStr;

        $stdStr .= " ";
        $fileStr .= " ";

        foreach ($recv->params as $param) {
            $stdStr .= $param->stdStr;
            $fileStr .= $param->fileStr;
        }

        $this->println($stdStr);
        if (!$recv->importFile) return;

        $dir = $this->getLogDir();
        if (!is_dir($dir)) {
            $mk = mkdir($dir, 0777, true);
            if (!$mk) {
                Log::sendToLogSocket('Failed to create folder `' . $dir . '`', 'log', Log::LEVEL_WARN);
                return;
            }
        }

        $path = $dir . '/' . $this->getLogFileName();

        $file = @fopen($path, 'a');
        if (!$file) {
            Log::sendToLogSocket('File `' . $path . '` does not have write access', 'log', Log::LEVEL_WARN);
            return;
        }

        $write = fwrite($file, $fileStr . PHP_EOL);
        if (!$write) {
            Log::sendToLogSocket('File `' . $path . '` failed to be written', 'log', Log::LEVEL_WARN);
            return;
        }
        fclose($file);
    }

    private function getLogDir(): string
    {
        return getcwd() . '/log/' . date('Ym');
    }

    private function getLogFileName(): string
    {
        return date('Ymd') . '.log';
    }

    private function println($str)
    {
        echo $str . PHP_EOL;
    }

    protected function timeFormat(string $time): LogParam
    {
        $param = new LogParam($time);
        return $param->addBrackets()->addColor("green");
    }

    protected function tagFormat(string $tag): LogParam
    {
        $param = new LogParam($tag);
        return $param->addBrackets()->addColor("cyan");
    }

    protected function workerIdFormat(string $tag): LogParam
    {
        $param = new LogParam($tag);
        return $param->addBrackets()->addColor("white");
    }

    protected function typeFormat(int $level): LogParam
    {

        if ($level === Log::LEVEL_WARN) {
            $param = new LogParam('WARN');
            return $param->addBrackets()->addColor("yellow");
        }
        if ($level === Log::LEVEL_ERROR) {
            $param = new LogParam('ERROR');
            return $param->addBrackets()->addColor("red");
        }

        if ($level === Log::LEVEL_DEBUG) {
            $param = new LogParam('DEBUG');
            return $param->addBrackets();
        }

        if ($level === Log::LEVEL_TRACE) {
            $param = new LogParam('TRACE');
            return $param->addBrackets();
        }

        $param = new LogParam('INFO');
        return $param->addBrackets();
    }


    /**
     * @var LogInfo|string $logInfo
     */
    public static function sendToLogSocket($logInfo, string $tag = 'master', int $level = Log::LEVEL_INFO)
    {
        $socket = Log::getInstance()->pool->getProcess(0)->exportSocket();

        if (is_string($logInfo)) {
            $msg = $logInfo;
            $logInfo = Log::newLog($tag, $level);
            $logInfo->addParam(new LogParam($msg));
        }

        $socket->send(json_encode($logInfo));
    }

    public static function setPool(Pool $pool, int $workerId)
    {
        $log = Log::getInstance();
        $log->pool = $pool;
        $log->workerId = $workerId;
    }

    public static function newLog(string $tag = 'master', int $level = Log::LEVEL_INFO): LogInfo
    {
        $log = Log::getInstance();
        $logInfo = new LogInfo($level, $tag, $log->workerId);
        $logInfo->importFile = $log->importFile;
        return $logInfo;
    }
}
