<?php

namespace Acms\Modules\Get\V2\Field;

use Acms\Modules\Get\V2\Base;

class Search extends Base
{
    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'field' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if ($this->Field === null) {
                return [];
            }
            $items = $this->buildFieldTrait($this->Field);
            return [
                'fields' => $items,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
