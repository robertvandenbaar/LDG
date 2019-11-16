<?php

namespace Ldg;

class Log
{
    protected static $logFile = BASE_DIR . '/data/log.json';

    public static function addEntry($type, $value)
    {
        if (!file_exists(self::$logFile)) {
            $log = array(self::creatLogEntry('info', 'Log file created on ' . strftime('%c')));
        } else {
            $log = json_decode(file_get_contents(self::$logFile), true);
        }

        array_unshift($log, self::creatLogEntry($type, $value));

        if (count($log) > 100) {
            $log = array_slice($log, 0, 100);
        }

        if (!file_put_contents(self::$logFile, json_encode($log, JSON_PRETTY_PRINT))) {
            throw new \Exception('Could not write log file: ' . self::$logFile);
        }

    }

    protected static function creatLogEntry($type, $value)
    {
        return array('type' => $type, 'date' => strftime('%c'), 'value' => $value);
    }

}