<?php

namespace Uccu\SwKoaLog;

use Psr\Log\LogLevel;

class LogParam
{

    public $rawStr;
    public $stdStr;
    public $fileStr;

    public function __construct(string $str, array $colors = [])
    {
        $this->rawStr = $str;
        $this->stdStr = $str;
        $this->fileStr = $str;
        $this->addColor(...$colors);
    }

    public function addColor(string ...$colors): LogParam
    {
        $consoleColor = new \PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor();
        foreach ($colors as $color) {
            $this->stdStr = $consoleColor->apply($color, $this->stdStr);
        }
        return $this;
    }

    public function addBrackets(): LogParam
    {
        $this->fileStr = '[' . $this->fileStr . ']';
        $this->stdStr = '[' . $this->stdStr . ']';
        return $this;
    }


    public static function timeFormat(string $time): LogParam
    {
        $param = new LogParam($time);
        return $param->addBrackets()->addColor("green");
    }

    public static function tagFormat(string $tag): LogParam
    {
        $param = new LogParam($tag);
        return $param->addBrackets()->addColor("cyan");
    }

    public static function workerIdFormat(int $tag): LogParam
    {
        $param = new LogParam($tag);
        return $param->addBrackets()->addColor("white");
    }

    public static function levelFormat($level): LogParam
    {

        $param = new LogParam(strtoupper($level));

        if (
            $level === LogLevel::WARNING
            || $level === LogLevel::NOTICE
        ) {
            return $param->addBrackets()->addColor("yellow");
        }

        if (
            $level === LogLevel::ERROR ||
            $level === LogLevel::CRITICAL ||
            $level === LogLevel::ALERT ||
            $level === LogLevel::EMERGENCY
        ) {
            return $param->addBrackets()->addColor("red");
        }

        return $param->addBrackets();
    }
}
