<?php

namespace Acms\Modules\Get\V2\Category;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Category\CategoryHelper;
use ACMS_RAM;
use RuntimeException;

class Field extends Base
{
    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'cid' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->cid) {
                throw new RuntimeException('Not found category id.');
            }
            $categoryHelper = new CategoryHelper($this->getBaseParams([]));

            if (!$categoryHelper->canAccessCategory($this->cid)) {
                throw new RuntimeException('Permission denied.');
            }
            $category = ACMS_RAM::category($this->cid);
            $vars = [
                'cid' => (int) $category['category_id'],
                'code' => $category['category_code'],
                'status' => $category['category_status'],
                'name' => $category['category_name'],
                'indexing' => $category['category_indexing'],
            ];
            $vars['fields'] = $this->buildFieldTrait(loadCategoryField($this->cid));
            $vars['moduleFields'] = $this->buildModuleField();
            $vars['geo'] = null;
            if (config('geolocation_category_function') === 'on') {
                $geo = loadGeometry('cid', $this->cid);
                $vars['geo'] = $this->buildFieldTrait($geo);
            } else {
                $vars['geo'] = null;
            }
            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }
}
