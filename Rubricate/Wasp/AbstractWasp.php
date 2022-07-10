<?php

namespace Rubricate\Wasp;

use Rubricate\Wasp\ConstWasp as C;

abstract class AbstractWasp
{
    abstract protected static function getDirFullPath();
    abstract protected static function getPrefixToFile();

    public static function info()
    {
        foreach (func_get_args() as $info) {
            self::set(C::TYPE_INFO, $info);
        }
    }

    public static function error()
    {
        foreach (func_get_args() as $error) {
            self::set(C::TYPE_ERROR, $error);
        }
    }

    public static function debug()
    {
        foreach (func_get_args() as $debug) {
            self::set(C::TYPE_DEBUG, $debug);
        }
    }

    public static function trace()
    {
        $type = null;
        $str  = '';

        foreach (func_get_args() as $log) {

            $str = self::getMessage($type, self::getVarDump($log));

            if (is_array($log) || is_object($log)) {
                $str = self::getMessage($type, print_r($log, true));
            }

            if (self::isException($log)) {
                $str = self::getMessage($type, $log->getTraceAsString());
            }

            if(is_string($log)){
                $str = self::getMessage($type, $log . PHP_EOL);
            }

            self::set(C::TYPE_TRACE, $str);
        }

        $trace = [];

        foreach (debug_backtrace() as $v) {

            foreach ($v['args'] as &$arg) {
                if (is_object($arg)) {
                    $arg = '(Object)';
                }
            }

            array_push($trace, array_filter($v, function($key) {
                return $key != 'object';
            }, ARRAY_FILTER_USE_KEY));
        }

        $str .= "Trace: " . print_r($trace, true);
        self::set(C::TYPE_TRACE, $str);
    }

    private static function set($type, $log)
    {
        $logdir = static::getDirFullPath();
        $file   = $logdir . self::getFile('_trace_');
        $str    = '';

        if ($type != C::TYPE_TRACE) {

            $str = self::getMessage($type, self::getVarDump($log));

            if (is_array($log) || is_object($log)) {
                $str = self::getMessage($type, print_r($log, true));
            }

            if (self::isException($log)) {
                $str = self::getMessage($type, $log->getTraceAsString());
            }

            if(is_string($log)){
                $str = self::getMessage($type, $log . PHP_EOL);
            }

            $file = $logdir . self::getFile();
        }

        @file_put_contents($file, $str, FILE_APPEND | FILE_TEXT);

        if (!file_exists($file)) {

            @chmod($file, C::STORAGE_MOD);

            foreach (new \DirectoryIterator($logdir) as $fileInfo) {

                if (
                    !$fileInfo->isDot() && !$fileInfo->isDir() &&
                    time() - $fileInfo->getCTime() >= C::CHANGE_TIME) {

                    @unlink($fileInfo->getRealPath());
                }
            }
        }
    }

    private static function getFile()
    {
        $p = trim(static::getPrefixToFile());
        $d = date('Y-m-d');
        $e = '.txt';

        return $p . $d . $e;
    }

    private static function getMessage($type, $message)
    {
        $d = date('Y-m-d H:i:s');
        $s = '%s %s: %s';

       return sprintf($s, $d, $type, $message);
    }

    private static function isException($log)
    {
        return (
            is_object($log) && (
                (get_class($log) == "Exception") ||
                is_subclass_of($log, "Exception")
            ));
    }

    public static function getVarDump($log)
    {
        ob_start(); var_dump($log);
        return ob_get_clean();
    }

}

