<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\UnitMultiLangTrait;
use Template;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Table extends Model implements AnkerUnitInterface
{
    use AnkerUnitTrait;
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
        return 'table';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('テーブル');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'table_source' => $this->getField1(),
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
        $this->setField1($this->implodeUnitDataTrait($request["table_source_{$id}"] ?? ''));
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if ($this->getField1() === '') {
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
        if ($this->getField1() === '') {
            return;
        }
        $vars += [
            'table' => $this->getField1(),
            'anker' => $this->getAnker(),
        ];
        $this->formatMultiLangUnitDataTrait($vars['table'], $vars, 'table');
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
        $this->formatMultiLangUnitDataTrait($this->getField1(), $vars, 'table');
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
            'table' => $this->getField1()
        ];
    }
}
