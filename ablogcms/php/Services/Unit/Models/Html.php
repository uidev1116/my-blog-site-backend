<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Template;

/**
 * @phpstan-type HtmlAttributes array{html: string}
 * @extends \Acms\Services\Unit\Contracts\Model<HtmlAttributes>
 */
class Html extends Model
{
    /**
     * ユニットの独自データ
     * @var HtmlAttributes
     */
    private $attributes = [
        'html' => '',
    ];

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        return 'html';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('自由入力');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            ...$this->attributes,
            'html' => $this->getField1(),
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
     * POSTデータからユニット独自データを抽出
     *
     * @param array $request
     * @return void
     */
    public function extract(array $request): void
    {
        $id = $this->getId();
        if (is_null($id)) {
            throw new \LogicException('Unit ID must be set before calling extract');
        }
        $this->setField1($request["html_html_{$id}"] ?? '');
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
        $html = $this->getField1();
        return $html;
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        return [strip_tags($this->getField1())];
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
        $html = $this->getField1();
        if ($html === '') {
            return;
        }
        $vars += [
            'html' => $html,
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
            'html' => $this->getField1(),
        ];
    }
}
