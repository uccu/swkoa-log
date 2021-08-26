<?php

namespace Uccu\SwKoaLog;


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
}
