<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\SizeableUnitTrait;
use Acms\Services\Unit\Contracts\SizeableUnitInterface;
use Acms\Traits\Unit\UnitMultiLangTrait;
use Template;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class YouTube extends Model implements AlignableUnitInterface, AnkerUnitInterface, SizeableUnitInterface
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
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'youtube';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('YouTube') . gettext('（非推奨）');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'youtube_id' => $this->getField2(),
            'youtube_size' => $this->getSize(),
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
        $this->setField2(config("{$configKeyPrefix}field_2", '', $configIndex));
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
        $youtubeId = $this->implodeUnitDataTrait($request["youtube_id_{$id}"] ?? '');
        if (preg_match(REGEX_VALID_URL, $youtubeId)) {
            $parsed_url = parse_url($youtubeId);
            if (!empty($parsed_url['query'])) {
                $youtubeId = preg_replace('/v=([\w\-_]+).*/', '$1', $parsed_url['query']) ?? '';
            }
        }
        $this->setField2($youtubeId);
        [$size, $displaySize] = $this->extractUnitSizeTrait($request["youtube_size_{$id}"] ?? '', $this::getUnitType());
        $this->setSize($size);
        $this->setField3($displaySize);
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
        $youtubeId = $this->getField2();
        if ($youtubeId === '') {
            return;
        }
        list($x, $y) = explode('x', $this->getSize());
        $vars += [
            'youtubeId' => $youtubeId,
            'x' => $x,
            'y' => $y,
            'align' => $this->getAlign()->value,
            'anker' => $this->getAnker(),
        ];
        $this->formatMultiLangUnitDataTrait($vars['youtubeId'], $vars, 'youtubeId');
        $vars = $this->displaySizeStyleTrait($this->getField3(), $vars);
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
        $this->renderSizeSelectTrait($this::getUnitType(), $this::getUnitType(), $size, $tpl, $rootBlock);
        $this->formatMultiLangUnitDataTrait($this->getField2(), $vars, 'youtubeId');
        $tpl->add(array_merge([$this::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'youtube_id' => $this->getField2(),
            'display_size' => $this->getField3()
        ];
    }
}
