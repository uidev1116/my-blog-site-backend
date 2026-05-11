<?php

namespace Acms\Services\Facades;

/**
 * @method static void debug(string $message, array $context = []) デバッグレベルのログを記録
 * @method static void info(string $message, array $context = []) 情報レベルのログを記録
 * @method static void notice(string $message, array $context = []) 通知レベルのログを記録
 * @method static void warning(string $message, array $context = []) 警告レベルのログを記録
 * @method static void error(string $message, array $context = []) エラーレベルのログを記録
 * @method static void critical(string $message, array $context = []) クリティカルレベルのログを記録
 * @method static void alert(string $message, array $context = []) アラートレベルのログを記録
 * @method static void emergency(string $message, array $context = []) 緊急レベルのログを記録
 * @method static void log(int|string $level, string $message, array $context = []) 任意レベルのログを記録
 */
class Logger extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'acms-logger';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
