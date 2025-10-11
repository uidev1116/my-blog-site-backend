<?php

namespace Acms\Modules\Get\V2\Module;

use Acms\Modules\Get\V2\Base;
use Exception;
use RuntimeException;

class Field extends Base
{
    /**
     * @return array|never
     */
    public function get(): array
    {
        try {
            if (!$this->mid) {
                throw new RuntimeException('Not found module id.');
            }
            $vars = [
                'mid' => $this->mid,
            ];
            $vars['fields'] = $this->buildModuleField();
            return $vars;
        } catch (Exception $e) {
            return [];
        }
    }
}
