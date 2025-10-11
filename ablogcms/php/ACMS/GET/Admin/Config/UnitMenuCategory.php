<?php

use Acms\Traits\Unit\UnitConfigTrait;

class ACMS_GET_Admin_Config_UnitMenuCategory extends ACMS_GET_Admin_Config
{
    use UnitConfigTrait;

    /**
     * @inheritDoc
     */
    public function get()
    {
        if (!IS_LICENSED) {
            return '';
        }
        if (!($rid = intval($this->Get->get('rid')))) {
            $rid = null;
        }
        if (!($setId = intval($this->Get->get('setid')))) {
            $setId = null;
        }

        $tpl    = new Template($this->tpl, new ACMS_Corrector());
        $Config =& $this->getConfig($rid, null, $setId);

        $baseOptions = array_map(
            function (array $category) {
                return [
                    'value' => $category['slug'],
                    'label' => $category['name'],
                ];
            },
            $this->getBaseCategoriesTrait()
        );

        $slugs = $Config->getArray('unit_menu_category_slug');
        $names = $Config->getArray('unit_menu_category_name');

        $options = array_map(function (?string $slug, ?string $name) {
            return [
                'value' => $slug ?? '',
                'label' => $name ?? '',
            ];
        }, $slugs, $names);

        $options = array_filter($options, function ($option) {
            return $option['value'] !== '';
        });

        return $tpl->render([
            'options' => array_merge($baseOptions, $options),
        ]);
    }
}
