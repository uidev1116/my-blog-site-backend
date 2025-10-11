<?php

namespace Acms\Services\Unit\Constants;

enum UnitAlign: string
{
    case CENTER = 'center';
    case LEFT = 'left';
    case RIGHT = 'right';
    case AUTO = 'auto';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- phpcsの誤検知
            self::CENTER => gettext('中央'),
            self::LEFT => gettext('左'),
            self::RIGHT => gettext('右'),
            self::AUTO => gettext('おまかせ'),
        };
    }
}
