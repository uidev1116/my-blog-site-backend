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
 * @phpstan-type MapAttributes array{msg: string, lat: float, lng: float, zoom: int, size: string, view_pitch: float, view_zoom: float, view_heading: float, view_activate: bool}
 * @extends \Acms\Services\Unit\Contracts\Model<MapAttributes>
 */
class Map extends Model implements AlignableUnitInterface, AnkerUnitInterface, SizeableUnitInterface
{
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use SizeableUnitTrait;

    /**
     * ユニットの独自データ
     * @var MapAttributes
     */
    private $attributes = [
        'msg' => '',
        'lat' => 35.185574,
        'lng' => 136.899066,
        'zoom' => 10,
        'size' => '',
        'view_pitch' => 0,
        'view_zoom' => 0,
        'view_heading' => 0,
        'view_activate' => false,
    ];

    /**
     * ユニットタイプを取得
     *
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'map';
    }

    /**
     * ユニットラベルを取得
     *
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('Googleマップ');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            ...$this->attributes,
            'msg' => $this->getMessage(),
            'lat' => $this->getLat(),
            'lng' => $this->getLng(),
            'zoom' => $this->getZoom(),
            'size' => $this->getSize(),
            'view_pitch' => $this->getViewPitch(),
            'view_zoom' => $this->getViewZoom(),
            'view_heading' => $this->getViewHeading(),
            'view_activate' => $this->getViewActivate(),
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
     * 緯度を取得
     *
     * @return float
     */
    private function getLat(): float
    {
        return (float)$this->getField2();
    }

    /**
     * 緯度をセット
     *
     * @param float $lat
     * @return void
     */
    private function setLat(float $lat): void
    {
        $this->setField2((string)$lat);
    }

    /**
     * 経度を取得
     *
     * @return float
     */
    private function getLng(): float
    {
        return (float)$this->getField3();
    }

    /**
     * 経度をセット
     *
     * @param float $lng
     * @return void
     */
    private function setLng(float $lng): void
    {
        $this->setField3((string)$lng);
    }

    /**
     * ズームレベルを取得
     *
     * @return int
     */
    private function getZoom(): int
    {
        return (int)$this->getField4();
    }

    /**
     * ズームレベルをセット
     *
     * @param int $zoom
     * @return void
     */
    private function setZoom(int $zoom): void
    {
        $this->setField4((string)$zoom);
    }

    /**
     * メッセージをセット
     *
     * @param string $message
     * @return void
     */
    private function setMessage(string $message): void
    {
        $this->setField1($message);
    }

    /**
     * 吹き出しHTMLを取得
     *
     * @return string
     */
    private function getMessage(): string
    {
        return str_replace([
            '"', '<', '>', '&'
        ], [
            '[[:quot:]]', '[[:lt:]]', '[[:gt:]]', '[[:amp:]]'
        ], $this->getField1());
    }

    /**
     * 吹き出しテキストを取得
     *
     * @return string
     */
    private function getMessageRaw(): string
    {
        return $this->getField1();
    }

    /**
     * ストリートビューのピッチを取得
     *
     * @return float
     */
    private function getViewPitch(): float
    {
        return (float)(explode(',', $this->getField7())[0] ?? 0);
    }

    /**
     * ストリートビューのズームを取得
     *
     * @return float
     */
    private function getViewZoom(): float
    {
        return (float)(explode(',', $this->getField7())[1] ?? 0);
    }

    /**
     * ストリートビューのヘディングを取得
     *
     * @return float
     */
    private function getViewHeading(): float
    {
        return (float)(explode(',', $this->getField7())[2] ?? 0);
    }

    /**
     * ストリートビューの情報をセット
     *
     * @param float $pitch
     * @param float $zoom
     * @param float $heading
     * @return void
     */
    private function setView(float $pitch, float $zoom, float $heading): void
    {
        $this->setField7("{$pitch},{$zoom},{$heading}");
    }

    /**
     * ストリートビューの情報をセット
     *
     * @param bool $value
     * @return void
     */
    private function setViewActivate(bool $value): void
    {
        $this->setField6($value ? 'true' : 'false');
    }

    /**
     * ストリートビューのアクティブフラグを取得
     *
     * @return bool
     */
    private function getViewActivate(): bool
    {
        return $this->getField6() === 'true';
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
        $this->setMessage(config("{$configKeyPrefix}field_1", '', $configIndex));
        $this->setLat((float)config("{$configKeyPrefix}field_2", '35.185574', $configIndex));
        $this->setLng((float)config("{$configKeyPrefix}field_3", '136.899066', $configIndex));
        $this->setZoom((int)config("{$configKeyPrefix}field_4", '10', $configIndex));
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
        $this->setMessage($request["map_msg_{$id}"] ?? '');
        $this->setLat((float)($request["map_lat_{$id}"] ?? 0));
        $this->setLng((float)($request["map_lng_{$id}"] ?? 0));
        $this->setZoom((int)($request["map_zoom_{$id}"] ?? 0));
        $viewActivate = ($request["map_view_activate_{$id}"] ?? '') === 'true';
        $this->setViewActivate($viewActivate);
        $this->setView(
            (float)($request["map_view_pitch_{$id}"] ?? 0),
            (float)($request["map_view_zoom_{$id}"] ?? 0),
            (float)($request["map_view_heading_{$id}"] ?? 0)
        );
        [$size, $displaySize] = $this->extractUnitSizeTrait($request["map_size_{$id}"] ?? '', $this::getUnitType());
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
            $this->getMessage() === '' &&
            $this->getLat() === 0.0 &&
            $this->getLng() === 0.0 &&
            $this->getZoom() === 0
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
        if ($this->getLat() === 0.0) {
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
     * @return array{
     *  lat: float,
     *  lng: float,
     *  zoom: int,
     *  msg: string,
     *  msgRaw: string,
     *  x: string,
     *  y: string,
     *  view_pitch: float,
     *  view_zoom: float,
     *  view_heading: float,
     *  view_activate: bool,
     * }
     */
    protected function getLegacy(): array
    {
        return $this->formatData();
    }

    /**
     * データを整形
     *
     * @return array{
     *  lat: float,
     *  lng: float,
     *  zoom: int,
     *  msg: string,
     *  msgRaw: string,
     *  x: string,
     *  y: string,
     *  view_pitch: float,
     *  view_zoom: float,
     *  view_heading: float,
     *  view_activate: bool,
     * }
     */
    protected function formatData(): array
    {
        list($x, $y) = array_pad(explode('x', $this->getSize()), 2, '');
        return [
            'lat' => $this->getLat(),
            'lng' => $this->getLng(),
            'zoom' => $this->getZoom(),
            'msg' => $this->getMessage(),
            'msgRaw' => $this->getMessageRaw(),
            'x' => $x,
            'y' => $y,
            'view_pitch' => $this->getViewPitch(),
            'view_zoom' => $this->getViewZoom(),
            'view_heading' => $this->getViewHeading(),
            'view_activate' => $this->getViewActivate(),
        ];
    }
}
