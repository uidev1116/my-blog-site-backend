<?php

namespace Acms\Traits\Unit;

use Template;

/**
 * サイズ機能を提供するトレイト
 */
trait SizeableUnitTrait
{
    /**
     * サイズ
     * @var string
     */
    private $size = '';

    /**
     * size getter
     *
     * @return string
     */
    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * size setter
     *
     * @param string $size
     * @return void
     */
    public function setSize(string $size): void
    {
        $this->size = $size;
    }

    /**
     * ユニット編集のサイズ選択肢を描画
     *
     * @param string $configType
     * @param string $templateType
     * @param string $size
     * @param Template $tpl
     * @param string[] $rootBlock
     * @return bool
     */
    protected function renderSizeSelectTrait(string $configType, string $templateType, string $size, Template $tpl, array $rootBlock = []): bool
    {
        $index = array_keys(configArray("column_{$configType}_size_label"));
        $matched = false;
        foreach ($index as $i) {
            $sizeVars  = [
                'value' => config("column_{$configType}_size", '', $i),
                'label' => config("column_{$configType}_size_label", '', $i),
                'display' => config("column_{$configType}_display_size", '', $i),
            ];
            if ($size === config("column_{$configType}_size", '', $i)) {
                $sizeVars['selected'] = config('attr_selected');
                $matched = true;
            }
            $tpl->add(array_merge(['size:loop', $templateType], $rootBlock), $sizeVars);
        }
        return $matched;
    }

    /**
     * ユニット幅のスタイルを描画
     *
     * @param string $size
     * @param array $vars
     * @return array
     */
    protected function displaySizeStyleTrait(string $size, array $vars): array
    {
        if ($size) {
            if (is_numeric($size) && intval($size) > 0) {
                $vars['display_size'] = ' style="width: ' . $size . '%"';
            } else {
                $viewClass = ltrim($size, '.');
                $vars['display_size_class'] = ' ' . $viewClass;
            }
        }
        return $vars;
    }

    /**
     * ユニットのサイズ設定を抜き出し
     *
     * @param string $newSize
     * @param string $configType
     * @return array{0: string, 1: string} 1つ目はサイズ、2つ目は表示サイズ ex: ['100', 'acms-col-12']
     */
    protected function extractUnitSizeTrait(string $newSize, string $configType): array
    {
        if (strpos($newSize, ':') !== false) {
            // v3.2.0以前はsize:display_sizeの形式でクライアントからのリクエストがある
            // そのため、「:」が含まれている場合は size:display_sizeの形式で抽出できるようになっている
            $size = preg_split('/:/', $newSize);
            if ($size === false) {
                return [$newSize, ''];
            }
            $result = array_pad($size, 2, '');
            return [$result[0], $result[1]];
        }
        $sizeOptions = configArray("column_{$configType}_size", true);
        $displaySizeOptions = configArray("column_{$configType}_display_size", true);
        $sizeToDisplaySize = array_combine($sizeOptions, $displaySizeOptions);
        // 3.2以降はサイズから、表示サイズを取得して返却する
        return [$newSize, $sizeToDisplaySize[$newSize] ?? ''];
    }
}
