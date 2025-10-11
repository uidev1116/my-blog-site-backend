<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\SizeableUnitTrait;
use Acms\Services\Unit\Contracts\SizeableUnitInterface;
use Template;

/**
 * @phpstan-type OsMapAttributes array{msg: string, lat: float, lng: float, zoom: int, size: string}
 * @extends \Acms\Services\Unit\Contracts\Model<OsMapAttributes>
 */
class OsMap extends Model implements AlignableUnitInterface, AnkerUnitInterface, SizeableUnitInterface
{
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use SizeableUnitTrait;

    /**
     * ユニットの独自データ
     * @var OsMapAttributes
     */
    private $attributes = [
        'msg' => '',
        'lat' => 35.185574,
        'lng' => 136.899066,
        'zoom' => 10,
        'size' => '',
    ];

    /**
     * ユニットタイプを取得
     *
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'osmap';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('標準マップ');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            ...$this->attributes,
            'msg' => $this->getField1(),
            'lat' => (float)$this->getField2(),
            'lng' => (float)$this->getField3(),
            'zoom' => (int)$this->getField4(),
            'size' => $this->getSize(),
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
     * 吹き出しHTMLを取得
     *
     * @return string
     */
    public function getMessage(): string
    {
        return str_replace([
            '"', '<', '>', '&'
        ], [
            '[[:quot:]]', '[[:lt:]]', '[[:gt:]]', '[[:amp:]]'
        ], $this->getField1());
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
        $this->setField2(config("{$configKeyPrefix}field_2", '35.185574', $configIndex));
        $this->setField3(config("{$configKeyPrefix}field_3", '136.899066', $configIndex));
        $this->setField4(config("{$configKeyPrefix}field_4", '10', $configIndex));
        $this->setField6(config("{$configKeyPrefix}field_6", '', $configIndex));
        $this->setField7(config("{$configKeyPrefix}field_7", '', $configIndex));
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
        $this->setField1($request["map_msg_{$id}"] ?? '');
        $this->setField2($request["map_lat_{$id}"] ?? '');
        $this->setField3($request["map_lng_{$id}"] ?? '');
        $this->setField4($request["map_zoom_{$id}"] ?? '');

        [$size, $displaySize] = $this->extractUnitSizeTrait($request["map_size_{$id}"] ?? '', 'map');
        $this->setSize($size);
        $this->setField5($displaySize);
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if (
            $this->getField1() === '' &&
            $this->getField2() === '' &&
            $this->getField3() === '' &&
            $this->getField4() === ''
        ) {
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
        $vars += $this->formatData();
        $vars['align'] = $this->getAlign()->value;
        $vars['anker'] = $this->getAnker();
        $vars = $this->displaySizeStyleTrait($this->getField5(), $vars);
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
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'msg' => $this->getField1(),
            'lat' => $this->getField2(),
            'lng' => $this->getField3(),
            'zoom' => $this->getField4(),
            'display_size' => $this->getField5(),
        ];
    }

    /**
     * データを整形
     *
     * @return array
     */
    protected function formatData(): array
    {
        list($x, $y) = array_pad(explode('x', $this->getSize()), 2, '');
        return [
            'lat' => $this->getField2(),
            'lng' => $this->getField3(),
            'zoom' => $this->getField4(),
            'msg' => $this->getMessage(),
            'msgRaw' => $this->getField1(),
            'x' => $x,
            'y' => $y,
        ];
    }
}
