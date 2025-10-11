<?php

declare(strict_types=1);

use Acms\Traits\Unit\UnitConfigTrait;
use Acms\Services\Facades\Application;

/**
 *
 * @phpstan-type SizeOption array{
 *   value: string,
 *   label: string,
 * }
 *
 * @phpstan-type TagOption array{
 *   value: string,
 *   label: string,
 * }
 *
 * @phpstan-type UnitDef array{
 *   id: string,
 *   label: string,
 * }
 */
class ACMS_GET_Admin_Unit_ConfigEditorSettings extends ACMS_GET_Admin_Config
{
    use UnitConfigTrait;

    /**
     * @inheritDoc
     */
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        if (!($rid = intval($this->Get->get('rid')))) {
            $rid = null;
        }
        if (!($mid = intval($this->Get->get('mid')))) {
            $mid = null;
        }
        if (!($setid = intval($this->Get->get('setid')))) {
            $setid = null;
        }
        if ($mid) {
            $setid = null;
        }

        if (!Config::isOperable($rid, $mid, $setid)) {
            return $tpl->render([
                'json' => json_encode([]),
            ]);
        }

        $config = $this->getConfig($rid, $mid, $setid);
        $json = [
            'unitGroup' => $this->getUnitGroupSetting($config),
            'align' => $this->getAlignSetting($config),
            'groupUnit' => $this->getGroupUnitSetting($config),
            'unitDefs' => $this->getUnitDefsSetting($config),
            'sizeOptions' => $this->getSizeOptionsSetting($config),
            'textTagOptions' => $this->getTextTagOptionsSetting($config),
        ];

        return $tpl->render([
            'json' => json_encode($json),
        ]);
    }

    /**
     * 機能設定
     *
     * @param \Field $config
     * @return array{
     *  enable: boolean,
     *  options: array{
     *    value: string,
     *    label: string,
     *  }[],
     * }
     */
    private function getUnitGroupSetting(\Field $config): array
    {
        $options = array_map(
            function (?string $class, ?string $label) {
                return [
                    'value' => $class ?? '',
                    'label' => $label ?? '',
                ];
            },
            $config->getArray('unit_group_class'),
            $config->getArray('unit_group_label')
        );
        $options = array_filter($options, function ($option) {
            return $option['value'] !== '';
        });
        $options = array_values($options);
        return [
            'enable' => $config->get('unit_group') === 'on',
            'options' => $options,
        ];
    }

    /**
     * 配置設定
     *
     * @param \Field $config
     * @return array{
     *   version: 'v1' | 'v2',
     * }
     */
    private function getAlignSetting(\Field $config): array
    {
        $version = $config->get('unit_align_version', 'v2');
        if (!in_array($version, ['v1', 'v2'], true)) {
            $version = 'v2';
        }
        return [
            'version' => $version,
        ];
    }

    /**
     * グループユニット設定
     *
     * @param \Field $config
     * @return array{
     *   classOptions: array{
     *     value: string,
     *     label: string,
     *   }[],
     *   tagOptions: array{
     *     value: string,
     *     label: string,
     *   }[],
     * }
     */
    private function getGroupUnitSetting(\Field $config): array
    {
        $classOptions = array_map(
            function (?string $class, ?string $label) {
                return [
                    'value' => $class ?? '',
                    'label' => $label ?? '',
                ];
            },
            $config->getArray('group_unit_class_value'),
            $config->getArray('group_unit_class_label')
        );

        $classOptions = array_filter($classOptions, function ($option) {
            return $option['value'] !== '';
        });
        $classOptions = array_values($classOptions);

        $tagOptions = array_map(
            function (?string $tag, ?string $label) {
                return [
                    'value' => $tag ?? '',
                    'label' => $label ?? '',
                ];
            },
            $config->getArray('group_unit_tag_value'),
            $config->getArray('group_unit_tag_label')
        );

        $tagOptions = array_filter($tagOptions, function ($option) {
            return $option['value'] !== '';
        });
        $tagOptions = array_values($tagOptions);

        return [
            'classOptions' => $classOptions,
            'tagOptions' => $tagOptions,
        ];
    }

    /**
     * ユニット定義設定を取得
     *
     * @param \Field $config
     * @return UnitDef[]
     */
    private function getUnitDefsSetting(\Field $config): array
    {
        $defs = array_map(
            function (
                ?string $id,
                ?string $label,
            ) {
                return [
                    'id' => $id ?? '',
                    'label' => $label ?? '',
                ];
            },
            $config->getArray('column_add_type'),
            $config->getArray('column_add_type_label'),
        );

        $defs = array_filter($defs, function ($def) {
            return $def['id'] !== '';
        });
        $registry = Application::make('unit-registry');
        assert($registry instanceof \Acms\Services\Unit\Registry);
        $defs = array_filter($defs, function ($def) use ($registry, $config) {
            $type = detectUnitTypeSpecifier($def['id']);
            if ($config->get('unit_group') === 'on' && $registry->isParentUnit($type)) {
                // ユニットグループが有効な場合、tree構造を表現するユニットは除外
                return false;
            }
            if (!$registry->exists($type)) {
                return false;
            }
            return true;
        });
        $defs = array_values($defs);

        return $defs;
    }

    /**
     * サイズオプション設定
     *
     * @param \Field $config
     * @return array<string, SizeOption[]>
     */
    private function getSizeOptionsSetting(\Field $config): array
    {
        $registry = Application::make('unit-registry');
        assert($registry instanceof \Acms\Services\Unit\Registry);
        $types = $registry->getSizableTypes();
        $sizeOptions = array_reduce($types, function (array $carry, string $type) use ($config) {
            $options = array_map(
                function (?string $size, ?string $label) {
                    return [
                        'value' => $size ?? '',
                        'label' => $label ?? '',
                    ];
                },
                $config->getArray("column_{$type}_size"),
                $config->getArray("column_{$type}_size_label")
            );
            $carry[$type] = $options;
            return $carry;
        }, []);
        return $sizeOptions;
    }

    /**
     * テキストタグオプション設定
     *
     * @param \Field $config
     * @return TagOption[]
     */
    private function getTextTagOptionsSetting(\Field $config): array
    {
        $options = array_map(
            function (?string $tag, ?string $label) {
                return [
                    'value' => $tag ?? '',
                    'label' => $label ?? '',
                ];
            },
            $config->getArray('column_text_tag'),
            $config->getArray('column_text_tag_label')
        );
        $options = array_filter($options, function ($option) {
            return $option['value'] !== '';
        });
        $options = array_values($options);
        return $options;
    }
}
