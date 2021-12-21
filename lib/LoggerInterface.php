<?php

namespace Uccu\SwKoaLog;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

interface LoggerInterface extends PsrLoggerInterface
{
    public function setConfig(array $config);
    public static function start(array $config);
}
