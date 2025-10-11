<?php

declare(strict_types=1);

namespace Acms\Services\Unit\Constants;

/**
 * ユニットステータスenum
 */
enum UnitStatus: string
{
    case OPEN = 'open';
    case CLOSE = 'close';
}
