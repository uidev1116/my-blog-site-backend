<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\SizeableUnitTrait;
use Acms\Services\Unit\Contracts\SizeableUnitInterface;
use Acms\Services\Facades\LocalStorage;
use Acms\Traits\Unit\UnitMultiLangTrait;
use Template;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class ExImage extends Model implements AlignableUnitInterface, AnkerUnitInterface, SizeableUnitInterface
{
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use SizeableUnitTrait;
    use UnitMultiLangTrait;

    /**
     * ユニットの独自データ
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        return 'eximage';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('画像URL');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'eximage_caption' => $this->getField1(),
            'eximage_normal' => $this->getField2(),
            'eximage_large' => $this->getField3(),
            'eximage_link' => $this->getField4(),
            'eximage_alt' => $this->getField5(),
            'eximage_size' => $this->getSize(),
            ...$this->attributes,
        ];
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * ユニットのデフォルト値をセット
     *
     * @param string $configKeyPrefix
     * @param int $configIndex
     * @return void
     */
    public function setDefault(string $configKeyPrefix, int $configIndex): void
    {
        $this->setField1(config("{$configKeyPrefix}field_1", '', $configIndex));
        $this->setField2(config("{$configKeyPrefix}field_2", '', $configIndex));
        $this->setField3(config("{$configKeyPrefix}field_3", '', $configIndex));
        $this->setField4(config("{$configKeyPrefix}field_4", '', $configIndex));
        $this->setField5(config("{$configKeyPrefix}field_5", '', $configIndex));
    }

    /**
     * @inheritDoc
     */
    public function extract(array $request): void
    {
        $id = $this->getId();
        if (is_null($id)) {
            throw new \LogicException('Unit ID must be set before calling extract');
        }
        $size = $request["eximage_size_{$id}"] ?? '';
        $normal = $request["eximage_normal_{$id}"] ?? '';
        $large = $request["eximage_large_{$id}"] ?? '';
        $displaySize = '';

        if (strpos($size, ':') !== false) {
            [$size, $displaySize] = preg_split('/:/', $size);
        }
        $normalPath = is_array($normal) ? $normal[0] : $normal;
        $largePath  = is_array($large) ? $large[0] : $large;
        if ('http://' != substr($normalPath, 0, 7) && 'https://' != substr($normalPath, 0, 8)) {
            $normalPath = rtrim(DOCUMENT_ROOT, '/') . $normalPath;
        }
        if ('http://' != substr($largePath, 0, 7) && 'https://' != substr($largePath, 0, 8)) {
            $largePath = rtrim(DOCUMENT_ROOT, '/') . $largePath;
        }
        if ($xy = LocalStorage::getImageSize($normalPath)) {
            if ($size !== '' && ($size < max($xy[0], $xy[1]))) {
                if ($xy[0] > $xy[1]) {
                    $x = $size;
                    $y = intval(floor(($size / $xy[0]) * $xy[1]));
                } else {
                    $y = $size;
                    $x = intval(floor(($size / $xy[1]) * $xy[0]));
                }
            } else {
                [$x, $y] = $xy;
            }
            $size = "{$x}x{$y}";
            if (!LocalStorage::getImageSize($largePath)) {
                $large = '';
            }
        } else {
            $normal = '';
        }
        if ($displaySize !== '') {
            $size = "{$size}:{$displaySize}";
        }
        $normal = $this->implodeUnitDataTrait($normal);
        $large = $this->implodeUnitDataTrait($large);

        $this->setField1($this->implodeUnitDataTrait($request["eximage_caption_{$id}"] ?? ''));
        $this->setField2($normal);
        $this->setField3($large);
        $this->setField4($this->implodeUnitDataTrait($request["eximage_link_{$id}"] ?? ''));
        $this->setField5($this->implodeUnitDataTrait($request["eximage_alt_{$id}"] ?? ''));

        [$size, $displaySize] = $this->extractUnitSizeTrait($size, $this::getUnitType());
        $this->setSize($size);
        $this->setField6($displaySize);
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if ($this->getField2() === '') {
            return false;
        }
        return true;
    }

    /**
     * ユニット複製時の専用処理
     *
     * @return void
     */
    public function handleDuplicate(): void
    {
    }

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    public function handleRemove(): void
    {
    }

    /**
     * キーワード検索用のワードを取得
     *
     * @return string
     */
    public function getSearchText(): string
    {
        return '';
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        return [];
    }

    /**
     * ユニットの描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    public function render(Template $tpl, array $vars, array $rootBlock): void
    {
        if ($this->getField2() === '') {
            return;
        }
        [$x, $y] = array_pad(explode('x', $this->getSize()), 2, '');
        $normalAry = $this->explodeUnitDataTrait($this->getField2());
        $linkAry = $this->explodeUnitDataTrait($this->getField4());
        $largeAry = $this->explodeUnitDataTrait($this->getField3());
        foreach ($normalAry as $i => $normal) {
            $j = $i === 0 ? '' : $i + 1;
            $eid = $this->getEntryId();
            $link_ = $linkAry[$i] ?? '';
            $large_ = $largeAry[$i] ?? '';
            $url = $link_ !== '' ? $link_ : ($large_ !== '' ? $large_ : null);

            if ($url !== null) {
                $linkVars = [
                    "url{$j}" => $url,
                    "link_eid{$j}" => $eid,
                ];
                if ($link_ === '') {
                    $linkVars["viewer{$j}"] = str_replace('{unit_eid}', strval($eid), config('entry_body_image_viewer'));
                }
                $tpl->add(array_merge(["link{$j}#front", 'unit#' . $this->getType()], $rootBlock), $linkVars);
                $tpl->add(array_merge(["link{$j}#rear", 'unit#' . $this->getType()], $rootBlock));
            }
        }
        $vars += [
            'normal' => $this->getField2(),
            'x' => $x,
            'y' => $y,
            'alt' => $this->getField5(),
            'large' => $this->getField3(),
            'caption' => '',
        ];
        $vars = $this->displaySizeStyleTrait($this->getField6(), $vars);
        $vars['caption'] = $this->getField1();
        $vars['align'] = $this->getAlign()->value;
        $vars['anker'] = $this->getAnker();
        $this->formatMultiLangUnitDataTrait($vars['normal'], $vars, 'normal');
        $this->formatMultiLangUnitDataTrait($x, $vars, 'x');
        $this->formatMultiLangUnitDataTrait($y, $vars, 'y');
        $this->formatMultiLangUnitDataTrait($vars['alt'], $vars, 'alt');
        $this->formatMultiLangUnitDataTrait($vars['large'], $vars, 'large');
        $this->formatMultiLangUnitDataTrait($vars['caption'], $vars, 'caption');

        $tpl->add(array_merge(['unit#' . $this->getType()], $rootBlock), $vars);
    }

    /**
     * 編集画面のユニット描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    public function renderEdit(Template $tpl, array $vars, array $rootBlock): void
    {
        $size = $this->getSize();
        if ($size) {
            [$x, $y] = array_pad(explode('x', $size), 2, 0);
            $size = max((int) $x, (int) $y);
        }
        $matched = $this->renderSizeSelectTrait(static::getUnitType(), static::getUnitType(), (string) $size, $tpl, $rootBlock);
        $vars += [
            'caption' => $this->getField1(),
            'large' => $this->getField3(),
            'link' => $this->getField4(),
            'alt' => $this->getField5(),
        ];
        if ($normal = $this->getField2()) {
            $vars['normal'] = $normal;
        }
        if (!$matched) {
            $vars['size:selected#none'] = config('attr_selected');
        }
        $this->formatMultiLangUnitDataTrait($this->getField1(), $vars, 'caption');
        $this->formatMultiLangUnitDataTrait($this->getField2(), $vars, 'normal');
        $this->formatMultiLangUnitDataTrait($this->getField3(), $vars, 'large');
        $this->formatMultiLangUnitDataTrait($this->getField4(), $vars, 'link');
        $this->formatMultiLangUnitDataTrait($this->getField5(), $vars, 'alt');

        $tpl->add(array_merge([static::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'caption' => $this->getField1(),
            'normal' => $this->getField2(),
            'large' => $this->getField3(),
            'link' => $this->getField4(),
            'alt' => $this->getField5(),
            'display_size' => $this->getField6(),
        ];
    }
}
