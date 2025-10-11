<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;

class Js extends Base
{
    /**
     * @inheritDoc
     */
    public function get(): array
    {
        Application::setIsAcmsJsLoaded(true);
        $jsModules = Common::getJsModules();
        $query = http_build_query($jsModules, '&');
        if (!empty($query)) {
            return [
                'arguments' => '?' . $query,
            ];
        }
        return [
            'arguments' => null,
        ];
    }
}
