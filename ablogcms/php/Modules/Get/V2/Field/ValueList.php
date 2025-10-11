<?php

namespace Acms\Modules\Get\V2\Field;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\FieldValueHelper;

class ValueList extends Base
{
    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'bid' => 'global',
        'field' => 'global',
    ];

    /**
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'self',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            $config = $this->loadModuleConfig();
            $limit = $this->limit ?? (int) $config->get('field_value-list_limit', 100);
            $order = strtoupper($config->get('field_value-list_order', 'ASC')) === 'ASC' ? 'ASC' : 'DESC';
            $fieldValueHelper = new FieldValueHelper($this->getBaseParams([]));

            $items = $fieldValueHelper->getFieldValueData($limit, $order);
            return [
                'items' => $items,
                'moduleFields' => $this->buildModuleField(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
