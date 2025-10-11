<?php

namespace Acms\Services\Update;

use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Update\Logger\Web as WebLooger;
use Acms\Services\Update\Logger\Auto as AutoLooger;
use RuntimeException;

class LoggerFactory
{
    public function createLogger(string $type): LoggerInterface
    {
        if ($type === 'web') {
            $logger = new WebLooger();
            $logger->setDestinationPath(CACHE_DIR . 'update-process.json');
            return $logger;
        }
        if ($type === 'auto') {
            return new AutoLooger();
        }
        throw new RuntimeException('システム更新用のロガーが見つかりませんでした');
    }
}
