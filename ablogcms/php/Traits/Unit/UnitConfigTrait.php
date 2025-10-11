<?php

declare(strict_types=1);

namespace Acms\Traits\Unit;

use Acms\Services\Unit\Constants\UnitBaseCategory;

trait UnitConfigTrait
{
    /**
     * ユニットのベースカテゴリーを取得
     *
     * @return array{
     *   slug: string,
     *   name: string,
     * }[]
     */
    protected function getBaseCategoriesTrait(): array
    {
        return array_map(
            function (array $category) {
                return [
                    'slug' => $category['value'],
                    'name' => $category['name'],
                ];
            },
            UnitBaseCategory::all()
        );
    }

    /**
     * ユニットの基本カテゴリーを取得
     *
     * @return array{
     *   slug: string,
     *   name: string,
     * }
     */
    protected function getBasicCategoryTrait(): array
    {
        $category = UnitBaseCategory::one(UnitBaseCategory::BASIC);
        return [
            'slug' => $category['value'],
            'name' => $category['name'],
        ];
    }

    /**
     * ユニット名を取得
     *
     * @param string $type
     * @return string
     */
    protected function getUnitNameTrait(string $type): string
    {
        $aryTypeLabel = [];
        foreach (configArray('column_add_type') as $i => $key) {
            $aryTypeLabel[$key] = config('column_add_type_label', '', $i);
        }
        return $aryTypeLabel[$type] ?? '';
    }
}
