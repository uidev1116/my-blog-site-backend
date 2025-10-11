<?php

use Acms\Services\Facades\Common;

class ACMS_GET_Admin_BlockEditor_SettingsJson extends ACMS_GET
{
    function get()
    {
        if (!sessionWithContribution()) {
            Common::responseJson([
                'features' => [],
                'blockMenus' => [],
                'fontSize' => [],
                'fontFamily' => [],
                'customClass' => [],
                'imageSizes' => [],
                'colorPalette' => [],
            ]);
        }
        Common::responseJson([
            'features' => $this->getFeaturesSetting(),
            'blockMenus' => $this->getBlockMenuSetting(),
            'fontSize' => $this->fontSizeSetting(),
            'fontFamily' => $this->fontFamilySetting(),
            'customClass' => $this->customClassSetting(),
            'imageSizes' => $this->imageSizesSetting(),
            'colorPalette' => $this->colorPaletteSetting(),
        ]);
    }

    /**
     * 機能設定
     *
     * @return array
     */
    protected function getFeaturesSetting(): array
    {
        return [
            'textItalic' => config('block_editor_block_menu_text_italic_display') === 'on',
            'textUnderline' => config('block_editor_block_menu_text_underline_display') === 'on',
            'textStrike' => config('block_editor_block_menu_text_strike_through_display') === 'on',
            'textCode' => config('block_editor_block_menu_text_code_display') === 'on',
            'textMarker' => config('block_editor_block_menu_text_marker_display') === 'on',
            'textColor' => config('block_editor_block_menu_text_color_display') === 'on',
            'fontSize' => config('block_editor_block_menu_font_size_display') === 'on',
            'fontFamily' => config('block_editor_block_menu_font_family_display') === 'on',
            'textSubscript' => config('block_editor_block_menu_text_sub_display') === 'on',
            'textSuperscript' => config('block_editor_block_menu_text_sup_display') === 'on',
            'customClass' => config('block_editor_block_menu_custom_class_display') === 'on',
            'tableBgColor' => config('block_editor_block_menu_table_bg_color_display') === 'on',
        ];
    }

    /**
     * ブロックメニュー設定
     *
     * @return array
     */
    protected function getBlockMenuSetting(): array
    {
        $data = [];
        $default = include(PHP_DIR . 'config/block-editor.php');
        $types = configArray('block_editor_block_menu_type');
        $currentGroup = 'グループ';
        $g = 1;
        foreach ($types as $i => $type) {
            $group = config('block_editor_block_menu_group', '', $i);
            // 空の group の場合は引き継ぐ
            if ($group !== '' && $currentGroup !== $group) {
                $currentGroup = $group;
            }
            // タイトルを設定（すでに存在するグループには再設定しない）
            if (!isset($data[$currentGroup])) {
                $data[$currentGroup] = [
                    'name' => "group-{$g}",
                    'title' => $currentGroup,
                    'commands' => [],
                ];
                $g++;
            }
            if (!isset($default[$type])) {
                continue;
            }
            $data[$currentGroup]['commands'][] = [
                'name' => $type,
                'label' => config('block_editor_block_menu_label', $default[$type]['label'], $i),
                'description' => $default[$type]['description'] ?? '',
                'class' => config('block_editor_block_menu_class', '', $i),
                'iconName' => $default[$type]['icon'] ?? '',
                'aliases' => $default[$type]['aliases'] ?? [],
                'isTextMenu' => $default[$type]['isTextMenu'] ?? false,
            ];
        }
        return array_values($data);
    }

    /**
     * フォントサイズ選択肢
     *
     * @return array
     */
    protected function fontSizeSetting(): array
    {
        $fontSizeLabel = configArray('block_editor_font_size_label');
        $fontSizeValue = configArray('block_editor_font_size_value');

        return array_map(function ($label, $value) {
            return [
                'label' => $label,
                'value' => $value,
            ];
        }, $fontSizeLabel, $fontSizeValue);
    }

    /**
     * フォントファミリー選択肢
     *
     * @return array
     */
    protected function fontFamilySetting(): array
    {
        $fontFamilyLabel = configArray('block_editor_font_family_label');
        $fontFamilyValue = configArray('block_editor_font_family_value');

        return array_map(function ($label, $value) {
            return [
                'label' => $label,
                'value' => $value,
            ];
        }, $fontFamilyLabel, $fontFamilyValue);
    }

    /**
     * カスタムクラス選択肢
     *
     * @return array
     */
    protected function customClassSetting(): array
    {
        $classListLabel = configArray('block_editor_custom_class_label');
        $classListValue = configArray('block_editor_custom_class_value');

        return array_map(function ($label, $value) {
            return [
                'label' => $label,
                'value' => $value,
            ];
        }, $classListLabel, $classListValue);
    }

    /**
     * 画像サイズ選択肢
     *
     * @return array
     */
    protected function imageSizesSetting(): array
    {
        $imageSizeLabel = configArray('block_editor_image_size_label');
        $imageSizeValue = configArray('block_editor_image_size_value');

        return array_map(function ($label, $value) {
            return [
                'label' => $label,
                'value' => $value,
            ];
        }, $imageSizeLabel, $imageSizeValue);
    }

    protected function colorPaletteSetting(): array
    {
        return configArray('block_editor_color_palette_value');
    }
}
