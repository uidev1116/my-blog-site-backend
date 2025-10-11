<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AnkerUnitTrait;
use Template;

/**
 * @phpstan-type CodeAttributes array{value: string}
 * @extends \Acms\Services\Unit\Contracts\Model<CodeAttributes>
 */
class Code extends Model implements AnkerUnitInterface
{
    use AnkerUnitTrait;

    /**
     * ユニットの独自データ
     * @var CodeAttributes
     */
    private $attributes = [
        'value' => '',
    ];

    /**
     * ユニットタイプを取得
     *
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'code';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('コード');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            ...$this->attributes,
            'value' => $this->getField1(),
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
        $this->setField1($request["code_value_{$id}"] ?? '');
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
        $value = $this->getField1();
        return $value;
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        $value = $this->getField1();
        return [strip_tags($value)];
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
        $value = $this->getField1();
        if ($value === '') {
            return;
        }
        $vars += [
            'value' => $value,
            'anker' => $this->getAnker(),
        ];

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
            'value' => $this->getField1(),
        ];
    }
}
