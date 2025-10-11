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
use ACMS_Hook;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Video extends Model implements AlignableUnitInterface, AnkerUnitInterface, SizeableUnitInterface
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
        return 'video';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('ビデオ');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'video_id' => $this->getField2(),
            'video_size' => $this->getSize(),
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
        $videoId = $this->implodeUnitDataTrait($request["video_id_{$id}"] ?? '');
        if (preg_match(REGEX_VALID_URL, $videoId)) {
            $tempVideoId = '';
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('extendsVideoUnit', [$videoId, &$tempVideoId]);
            }
            if (is_string($tempVideoId) && $tempVideoId !== '') { // @phpstan-ignore-line
                $videoId = $tempVideoId;
            } else {
                $parsed_url = parse_url($videoId);
                if (!empty($parsed_url['query'])) {
                    $videoId = preg_replace('/v=([\w\-_]+).*/', '$1', $parsed_url['query']) ?? '';
                }
            }
        }
        $this->setField2($videoId);
        [$size, $displaySize] = $this->extractUnitSizeTrait($request["video_size_{$id}"] ?? '', $this::getUnitType());
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
        if (empty($youtubeId)) {
            return;
        }
        [$x, $y] = array_pad(explode('x', $this->getSize()), 2, '');
        $vars += [
            'videoId' => $youtubeId,
            'x' => $x,
            'y' => $y,
            'align' => $this->getAlign()->value,
            'anker' => $this->getAnker(),
        ];
        $this->formatMultiLangUnitDataTrait($vars['videoId'], $vars, 'videoId');
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
        $this->renderSizeSelectTrait($this::getUnitType(), $this::getUnitType(), $this->getSize(), $tpl, $rootBlock);
        $this->formatMultiLangUnitDataTrait($this->getField2(), $vars, 'videoId');

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
            'video_id' => $this->getField2(),
            'display_size' => $this->getField3()
        ];
    }
}
