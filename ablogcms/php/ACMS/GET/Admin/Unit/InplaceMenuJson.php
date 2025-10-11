<?php

declare(strict_types=1);

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Application;

/**
 * @phpstan-type MenuItem array{
 *   type: string,
 *   label: string,
 * }
 */
class ACMS_GET_Admin_Unit_InplaceMenuJson extends ACMS_GET
{
    /**
     * @inheritDoc
     */
    public function get()
    {
        if (!sessionWithContribution()) {
            return Common::responseJson([]);
        }
        $menu = $this->getMenu();

        return Common::responseJson($menu);
    }

    /**
     * ダイレクト編集用のユニットメニューを取得
     *
     * @return MenuItem[]
     */
    private function getMenu(): array
    {
        $menu = array_map(
            function (
                ?string $type,
                ?string $label
            ) {
                return [
                    'type' => $type ?? '',
                    'label' => $label ?? '',
                ];
            },
            configArray('column_add_type'),
            configArray('column_add_type_label'),
        );

        $menu = array_filter($menu, function (array $menuItem) {
            return $menuItem['type'] !== '';
        });

        $registory = Application::make('unit-registry');
        assert($registory instanceof \Acms\Services\Unit\Registry);
        $menu = array_filter($menu, function (array $menuItem) use ($registory) {
            // ダイレクト編集は1つのユニットしか追加できないため、パターンとして登録されているユニットは除外
            $type = detectUnitTypeSpecifier($menuItem['type']); // 特定指定子を除外した、一般名のユニット種別
            return $registory->exists($type);
        });

        $menu = array_filter($menu, function (array $menuItem) use ($registory) {
            $type = detectUnitTypeSpecifier($menuItem['type']); // 特定指定子を除外した、一般名のユニット種別
            return $registory->isInplaceSupported($type);
        });

        $menu = array_values($menu);

        return $menu;
    }
}
