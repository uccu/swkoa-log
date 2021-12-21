<?php

namespace Uccu\SwKoaLog;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Swoole\Process\Pool;
use Swoole\Process;
use Swoole\Coroutine\Socket;

abstract class Logger implements LoggerInterface
{

    use LoggerTrait;


    /**
     * 进程池
     * @var Pool|null $pool
     */
    protected $pool;
    protected $workerId;
    protected $masterWorkerId;
    protected $tag;
    protected $importFile = true;


    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, $message, array $context = array())
    {
        $info = $this->interpolate($message, $context);
        $this->sendToLogSocket($info, $level);
    }


    /**
     * @var LogInfo|string $logInfo
     */
    public function sendToLogSocket($logInfo, int $level = LogLevel::INFO)
    {

        $socket = $this->pool->getProcess($this->masterWorkerId)->exportSocket();

        if (is_string($logInfo)) {
            $msg = $logInfo;
            $logInfo = $this->newLog($level);
            $logInfo->addParam($this->newLogParam($msg));
        }

        $socket->send(json_encode($logInfo));
    }


    public function setConfig(array $conf)
    {
        foreach ($conf as $k => $c) {
            $this->$k = $c;
        }
    }

    /**
     * 开启日志服务
     * @var array $config 配置
     */
    public static function start(array $config)
    {

        $log = new static;

        $log->setConfig([
            'pool' => $config['pool'],
            'workerId' => $config['workerId'],
            'masterWorkerId' => $config['masterWorkerId'],
            'tag' => 'master',
            'importFile' => false
        ]);

        /**
         * @var Process $process
         */
        $process = $log->pool->getProcess();

        /**
         * @var Socket $socket
         */
        $socket = $process->exportSocket();

        while (1) {
            $recv = $socket->recv(65535, 3600);
            if ($recv === "" || $recv === false) {
                continue;
            }
            $recv = json_decode($recv);
            $log->output($recv);
        }
    }

    /**
     * @param LogInfo $recv
     */
    protected function output($recv)
    {

        $stdStr = "";
        $fileStr = "";

        $time = LogParam::timeFormat($recv->time);
        $stdStr .= $time->stdStr;
        $fileStr .= $time->fileStr;

        $tag = LogParam::tagFormat($recv->tag);
        $stdStr .= $tag->stdStr;
        $fileStr .= $tag->fileStr;

        $workerId = LogParam::workerIdFormat($recv->workerId);
        $stdStr .= $workerId->stdStr;
        $fileStr .= $workerId->fileStr;

        $level = LogParam::levelFormat($recv->level);
        $stdStr .= $level->stdStr;
        $fileStr .= $level->fileStr;

        $stdStr .= " ";
        $fileStr .= " ";

        foreach ($recv->params as $param) {
            $stdStr .= $param->stdStr;
            $fileStr .= $param->fileStr;
        }


        static::stdout($stdStr);
        if (!$recv->importFile) return;

        $dir = $this->getLogDir($recv);
        if (!is_dir($dir)) {
            $mk = mkdir($dir, 0777, true);
            if (!$mk) {
                $this->warning('Failed to create folder `' . $dir . '`');
                return;
            }
        }

        $path = $dir . '/' . $this->getLogFileName($recv);

        $file = @fopen($path, 'a');
        if (!$file) {
            $this->warning('File `' . $path . '` does not have write access');
            return;
        }

        $write = fwrite($file, $fileStr . PHP_EOL);
        if (!$write) {
            $this->warning('File `' . $path . '` failed to be written');
            return;
        }
        fclose($file);
    }

    /**
     * @var LogInfo $recv
     */
    abstract protected function getLogDir($recv): string;

    /**
     * @var LogInfo $recv
     */
    abstract protected function getLogFileName($recv): string;

    protected static function stdout($str)
    {
        echo $str . PHP_EOL;
    }

    protected function interpolate($message, array $context = array())
    {
        $replace = array();
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }


    public function newLog($level = LogLevel::INFO): LogInfo
    {
        $logInfo = new LogInfo($level, $this->tag, $this->workerId);
        $logInfo->importFile = $this->importFile;
        return $logInfo;
    }

    public static function newLogParam(string $str, array $colors = []): LogParam
    {
        return new LogParam($str, $colors);
    }
}
