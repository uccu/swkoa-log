<?php

namespace Uccu\SwKoaLog;

use Psr\Log\LogLevel;
use Psr\Log\LoggerTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Swoole\Process;
use Swoole\Process\Pool;
use Swoole\Process\Manager;
use Swoole\Coroutine\Socket;
use Uccu\SwKoaPlugin\IHttpServerStartBeforePlugin;
use Uccu\SwKoaPlugin\IPoolStartBeforePlugin;

abstract class Logger implements LoggerInterface, IPoolStartBeforePlugin, IHttpServerStartBeforePlugin
{

    use LoggerTrait;

    /**
     * 进程池
     * @var Pool
     */
    protected $pool;

    /**
     * 当前进程ID
     * @var int
     */
    protected $workerId;

    /**
     * Logger进程ID
     * @var int
     */
    protected $masterWorkerId;

    /**
     * 标签
     * @var string
     */
    protected $tag;

    /**
     * 是否导入文件
     * @var bool
     */
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
     * @throws InvalidArgumentException
     */
    public function log($level, $message, array $context = array())
    {

        if (!in_array($level, [
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL,
            LogLevel::ERROR, LogLevel::WARNING, LogLevel::NOTICE,
            LogLevel::INFO, LogLevel::DEBUG
        ])) {
            throw new InvalidArgumentException('Not found logger level: ' . $level);
        }

        $info = $this->interpolate($message, $context);
        $this->sendToLogSocket($info, $level);
    }


    /**
     * @var LogInfo|string $logInfo
     */
    protected function sendToLogSocket($logInfo, int $level = LogLevel::INFO)
    {

        if (is_string($logInfo)) {
            $msg = $logInfo;
            $logInfo = $this->newLog($level);
            $logInfo->addParam($this->newLogParam($msg));
        }

        if (is_null($this->pool) || is_null($this->masterWorkerId)) {
            $this->output($logInfo);
            return;
        }

        $socket = $this->pool->getProcess($this->masterWorkerId)->exportSocket();
        $socket->send(json_encode($logInfo));
    }


    public function setConfig(array $conf)
    {
        foreach ($conf as $k => $c) {
            $this->$k = $c;
        }
    }


    public function httpServerStartBefore($httpServer)
    {
        $this->setConfig([
            'pool' => $httpServer->pool,
            'workerId' => $httpServer->workerId,
            'masterWorkerId' => 0,
            'tag' => 'http'
        ]);
    }

    public function poolStartBefore($manager)
    {

        $appName = "Uccu\\SwKoaServer\\App";
        if (class_exists($appName)) {
            $appName::$config = $this;
        }

        $manager->add(function (Pool $pool, int $workerId) {

            $this->setConfig([
                'pool' => $pool,
                'workerId' => $workerId,
                'masterWorkerId' => $workerId,
                'tag' => 'master',
                'importFile' => false
            ]);

            /**
             * @var Process $process
             */
            $process = $this->pool->getProcess();

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
                $this->output($recv);
            }
        });
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
                if (is_null($this->pool) || is_null($this->masterWorkerId)) {
                    $this->importFile = false;
                }
                $this->warning('Failed to create folder `' . $dir . '`');
                return;
            }
        }

        $path = $dir . '/' . $this->getLogFileName($recv);

        $file = @fopen($path, 'a');
        if (!$file) {
            if (is_null($this->pool) || is_null($this->masterWorkerId)) {
                $this->importFile = false;
            }
            $this->warning('File `' . $path . '` does not have write access');
            return;
        }

        $write = fwrite($file, $fileStr . PHP_EOL);
        if (!$write) {
            if (is_null($this->pool) || is_null($this->masterWorkerId)) {
                $this->importFile = false;
            }
            $this->warning('File `' . $path . '` failed to be written');
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
