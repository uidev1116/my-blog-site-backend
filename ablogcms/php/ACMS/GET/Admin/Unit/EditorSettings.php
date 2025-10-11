<?php

declare(strict_types=1);

use Acms\Traits\Unit\UnitConfigTrait;
use Acms\Services\Facades\Application;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;

/**
 * @phpstan-type SizeOption array{
 *   value: string,
 *   label: string,
 * }
 *
 * @phpstan-type Unit array{
 *   id: string,
 *   name: string,
 *   group?: string,
 *   align?: string,
 *   attributes: array<string, mixed>,
 * }
 *
 * @phpstan-type UnitCategory array{
 *   slug: string,
 *   name: string,
 * }
 *
 * @phpstan-type UnitDef array{
 *   id: string,
 *   label: string,
 *   icon?: string,
 *   category: UnitCategory,
 *   units: Unit[]
 * }
 */
class ACMS_GET_Admin_Unit_EditorSettings extends ACMS_GET
{
    use UnitConfigTrait;

    /**
     * @inheritDoc
     */
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        if (!sessionWithContribution()) {
            return $tpl->render([
                'json' => json_encode([]),
            ]);
        }
        $json = [
            'unitGroup' => $this->getUnitGroupSetting(),
            'align' => $this->getAlignSetting(),
            'groupUnit' => $this->getGroupUnitSetting(),
            'sizeOptions' => $this->getSizeOptionsSetting(),
            'unitDefs' => $this->getUnitDefsSetting(),
        ];

        return $tpl->render([
            'json' => json_encode($json),
        ]);
    }

    /**
     * 機能設定
     *
     * @return array{
     *  enable: boolean,
     *  options: array{
     *    value: string,
     *    label: string,
     *  }[],
     * }
     */
    private function getUnitGroupSetting(): array
    {
        $options = array_map(
            function (?string $class, ?string $label) {
                return [
                    'value' => $class ?? '',
                    'label' => $label ?? '',
                ];
            },
            configArray('unit_group_class'),
            configArray('unit_group_label')
        );
        $options = array_filter($options, function ($option) {
            return $option['value'] !== '';
        });
        $options = array_values($options);
        return [
            'enable' => config('unit_group') === 'on',
            'options' => $options,
        ];
    }

    /**
     * 配置設定
     *
     * @return array{
     *   version: 'v1' | 'v2',
     * }
     */
    private function getAlignSetting(): array
    {
        $version = config('unit_align_version', 'v2');
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
    private function getGroupUnitSetting(): array
    {
        $classOptions = array_map(
            function (?string $class, ?string $label) {
                return [
                    'value' => $class ?? '',
                    'label' => $label ?? '',
                ];
            },
            configArray('group_unit_class_value'),
            configArray('group_unit_class_label')
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
            configArray('group_unit_tag_value'),
            configArray('group_unit_tag_label')
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
     * @return UnitDef[]
     */
    private function getUnitDefsSetting(): array
    {
        $defs = array_map(
            function (
                ?string $id,
                ?string $label,
                ?string $icon,
                ?string $slug
            ) {
                return [
                    'id' => $id ?? '',
                    'label' => $label ?? '',
                    'icon' => $icon ?? '',
                    'category' => $slug !== null ? $this->getCategoryBySlug($slug) : $this->getBasicCategoryTrait(),
                    'units' => $id !== null ? $this->getUnitsById($id) : [],
                ];
            },
            configArray('column_add_type'),
            configArray('column_add_type_label'),
            configArray('column_add_type_icon'),
            configArray('column_add_type_category_slug'),
        );

        $defs = array_filter($defs, function ($def) {
            return $def['id'] !== '' && count($def['units']) > 0;
        });
        $registry = Application::make('unit-registry');
        assert($registry instanceof \Acms\Services\Unit\Registry);
        $defs = array_filter($defs, function ($def) use ($registry) {
            $type = detectUnitTypeSpecifier($def['id']);
            if (config('unit_group') === 'on' && $registry->isParentUnit($type)) {
                // ユニットグループが有効な場合、tree構造を表現するユニットは除外
                return false;
            }
            return true;
        });
        $defs = array_values($defs);

        if ($this->shouldShowMoreUnit()) {
            // 続きを読む / 会員限定ユニットを追加
            $defs[] = $this->getMoreUnitDef();
        }

        return $defs;
    }

    /**
     * カテゴリーを取得
     *
     * @param string $slug
     * @return UnitCategory
     */
    private function getCategoryBySlug(string $slug): array
    {
        $categories = array_map(
            function (?string $slug, ?string $name) {
                return [
                    'slug' => $slug ?? '',
                    'name' => $name ?? '',
                ];
            },
            configArray('unit_menu_category_slug'),
            configArray('unit_menu_category_name')
        );
        $categories = array_merge($this->getBaseCategoriesTrait(), $categories);
        $category = array_find(
            $categories,
            function ($category) use ($slug) {
                return $category['slug'] !== '' && $category['slug'] === $slug;
            }
        );

        if ($category !== null) {
            return $category;
        }

        return $this->getBasicCategoryTrait();
    }

    /**
     * @param string $id
     * @return Unit[]
     */
    private function getUnitsById(string $id): array
    {
        $unitTypes = configArray('column_def_add_' . $id . '_type');
        $unitTypes = array_filter($unitTypes, function ($type) {
            return is_string($type) && $type !== '';
        });
        $unitTypes = array_values($unitTypes);
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);
        $units = array_map(
            function (string $type, int $index) use ($unitRepository, $id) {
                $unit = $unitRepository->create($type, $id, $index);
                if ($unit === null) {
                    return null;
                }
                $data = [
                    'id' => $unit->getType(),
                    'name' => $unit->getName(),
                    'attributes' => $unit->getAttributes(),
                ];
                if (config('unit_group') === 'on') {
                    $data['group'] = $unit->getGroup();
                }
                if ($unit instanceof AlignableUnitInterface) {
                    $data['align'] = $unit->getAlign()->value;
                }
                return $data;
            },
            $unitTypes,
            array_keys($unitTypes)
        );

        $units = array_filter($units, function ($unit) {
            return $unit !== null;
        });
        $units = array_values($units);

        return $units;
    }

    /**
     * 続きを読む / 会員限定ユニットを表示するかどうか
     *
     * @return bool
     */
    private function shouldShowMoreUnit(): bool
    {
        if (!in_array(ADMIN, ['entry_editor', 'entry-edit'], true)) {
            // 編集画面以外は表示しない
            return false;
        }
        if (config('entry_edit_summary_range_display') !== 'true') {
            // 続きを読む / 会員限定を表示しない
            return false;
        }
        return true;
    }

    /**
     * 続きを読む / 会員限定ユニットのラベルを取得
     *
     * @return UnitDef
     */
    private function getMoreUnitDef(): array
    {
        $label = config('entry_edit_summary_range_label') !== ''
            ? config('entry_edit_summary_range_label')
            : gettext('続きを読む / 会員限定');
        $icon = config('entry_edit_summary_range_icon') !== ''
            ? config('entry_edit_summary_range_icon')
            : 'more_horiz';
        $categorySlug = config('entry_edit_summary_range_category_slug');
        return [
            'id' => 'more',
            'label' => $label,
            'icon' => $icon,
            'category' => $this->getCategoryBySlug($categorySlug),
            'units' => [
                [
                    'id' => 'more',
                    'name' => $label,
                    'attributes' => [],
                ],
            ],
        ];
    }

    /**
     * サイズオプション設定
     *
     * @return array<string, SizeOption[]>
     */
    private function getSizeOptionsSetting(): array
    {
        $registry = Application::make('unit-registry');
        assert($registry instanceof \Acms\Services\Unit\Registry);
        $types = $registry->getSizableTypes();
        $sizeOptions = array_reduce($types, function (array $carry, string $type) {
            $options = array_map(
                function (?string $size, ?string $label) {
                    return [
                        'value' => $size ?? '',
                        'label' => $label ?? '',
                    ];
                },
                configArray("column_{$type}_size"),
                configArray("column_{$type}_size_label")
            );
            $carry[$type] = $options;
            return $carry;
        }, []);
        return $sizeOptions;
    }
}
