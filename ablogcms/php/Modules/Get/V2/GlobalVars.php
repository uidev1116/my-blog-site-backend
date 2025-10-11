<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;

class GlobalVars extends Base
{
    /**
     * @inheritDoc
     */
    public function get(): array
    {
        return [
            'vars' => $this->getGlobals(),
            'moduleFields' => $this->buildModuleField(),
        ];
    }

    /**
     * グローバル変数を取得
     *
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $globalVarsList = globalVarsList();
        $globalVars = [];
        foreach ($globalVarsList as $key => $val) {
            $key = preg_replace('/^\%\{([^\}]+)\}/', '$1', $key);
            if (in_array($key, ['SID', 'UA', 'CH_UA', 'CH_UA_MOBILE', 'CH_UA_PLATFORM', 'REMOTE_ADDR', 'SESSION_USER_MAIL'], true)) {
                // 特定のキーは除外
                continue;
            }
            $globalVars[$key] = $val;
        }
        return $globalVars;
    }
}
