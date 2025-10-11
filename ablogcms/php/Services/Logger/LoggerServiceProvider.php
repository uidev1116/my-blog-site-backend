<?php

namespace Acms\Services\Logger;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Logger\Handler\EmailHandler;
use Acms\Services\Logger\Handler\DatabaseHandler;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;

class LoggerServiceProvider extends ServiceProvider
{
    /**
     * register service
     *
     * @param \Acms\Services\Container $container
     *
     * @return void
     */
    public function register(Container $container): void
    {
        $logger = new Logger('acms-logger');

        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new MemoryPeakUsageProcessor());

        // Rotating file handler
        if (defined('ERROR_LOG_FILE') && ERROR_LOG_FILE) {
            $path = SCRIPT_DIR . ERROR_LOG_FILE;
            $maxFiles = intval(env('LOGGER_ROTATING_MAX_FILES', '60'));
            $this->setHandler($logger, new RotatingFileHandler($path, $maxFiles, Logger::NOTICE));
        }

        // E-mail handler
        $reportingLevel = env('ALERT_REPORTING_LEVEL', 'WARNING');
        if ($reportingLevelValue = Level::getLevelValue($reportingLevel)) {
            $this->setHandler($logger, new EmailHandler($reportingLevelValue));
        }

        // Database handler
        $this->setHandler($logger, new DatabaseHandler(Logger::DEBUG));

        // bind
        $container->singleton('acms-logger', function () use ($logger) {
            return $logger;
        });

        $container->singleton('acms-logger-filter', function () {
            return new Filter([
                'password',
                'passwd',
                'pass',
                'retype_pass',
                'code',
                'recovery',
                'takeover',
                'token',
                'api_key',
                'secret',
                'formUniqueToken',
                'formToken',
            ], '***MASKED***');
        });

        $container->singleton('acms-logger-repository', Repository::class);
    }

    /**
     * initialize service
     *
     * @return void
     */
    public function init(): void
    {
    }

    /**
     * ハンドラーをロガーにセット
     * @param \Monolog\Logger $logger
     * @param \Monolog\Handler\AbstractProcessingHandler $handler
     * @return void
     */
    protected function setHandler($logger, $handler): void
    {
        $formatter = new LineFormatter();
        $formatter->includeStacktraces(true);
        $logger->pushHandler($handler->setFormatter($formatter));
    }
}
