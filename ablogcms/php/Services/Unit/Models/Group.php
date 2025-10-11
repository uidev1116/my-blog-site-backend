<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\ParentUnit;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AnkerUnitTrait;
use Template;

/**
 * @phpstan-type GroupAttributes array{class: string, tag: string}
 * @extends \Acms\Services\Unit\Contracts\Model<GroupAttributes>
 */
class Group extends Model implements ParentUnit, AnkerUnitInterface
{
    use AnkerUnitTrait;

    /**
     * グループユニットの独自データ
     * @var GroupAttributes
     */
    private $attributes = [
        'class' => '',
        'tag' => 'div',
    ];

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        return 'group';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('グループ');
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
        $this->setAttribute('class', config("{$configKeyPrefix}class", '', $configIndex));
        $this->setAttribute('tag', config("{$configKeyPrefix}tag", 'div', $configIndex));
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
        $this->setAttribute('class', $request["group_class_{$id}"] ?? '');
        $this->setAttribute('tag', $request["group_tag_{$id}"] ?? 'div');
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
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
        $tpl->add(
            array_merge(['unit#' . $this->getType()], $rootBlock),
            array_merge(
                $vars,
                $this->getAttributes(),
                [
                    'anker' => $this->getAnker(),
                ]
            )
        );
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
        $tpl->add(array_merge([static::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return array_merge($this->attributes, [
            'tag' => $this->safeTagName($this->attributes['tag']),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes): void
    {
        $this->attributes = array_merge($attributes, [
            'tag' => $this->safeTagName($attributes['tag']),
        ]);
    }

    /**
     * グループユニットの独自データを取得
     *
     * @param key-of<GroupAttributes> $key
     * @return value-of<GroupAttributes>
     */
    public function getAttribute(string $key): string
    {
        if ($key === 'tag') {
            return $this->safeTagName($this->attributes[$key]);
        }
        return $this->attributes[$key];
    }

    /**
     * グループユニットの独自データを設定
     *
     * @param key-of<GroupAttributes> $key
     * @param value-of<GroupAttributes> $value
     * @return void
     */
    public function setAttribute(string $key, string $value): void
    {
        if ($key === 'tag') {
            $value = $this->safeTagName($value);
        }
        $this->attributes[$key] = $value;
    }

    /**
     * HTMLタグ名をサニタイズする
     *
     * @param string $tagName
     * @return string
     */
    private function safeTagName(string $tagName): string
    {
        // 許可するタグのホワイトリスト
        $allowedTagNames = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav'];
        return in_array($tagName, $allowedTagNames, true) ? $tagName : 'div';
    }

    /**
     * ユニットロード時に拡張処理を行います
     *
     * @param array $record
     * @return void
     */
    public function onLoad(array $record): void
    {
        $this->setAttribute('class', $record['column_group_class'] ?? '');
        $this->setAttribute('tag', $record['column_group_tag'] ?? 'div');
    }

    /**
     * @inheritDoc
     */
    public function extendInsertQuery(\SQL_Insert &$sql, bool $isRevision): void
    {
        $sql->addInsert('column_group_class', $this->getAttribute('class'));
        $sql->addInsert('column_group_tag', $this->getAttribute('tag'));
    }
}
