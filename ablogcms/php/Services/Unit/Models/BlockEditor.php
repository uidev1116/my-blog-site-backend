<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Facades\BlockEditor as BlockEditorHelper;
use Acms\Services\Unit\Contracts\ConfigProcessable;
use Acms\Services\Unit\Contracts\ExportEntry;
use Template;

/**
 * @phpstan-type BlockEditorAttributes array{html: string}
 * @extends \Acms\Services\Unit\Contracts\Model<BlockEditorAttributes>
 */
class BlockEditor extends Model implements ConfigProcessable, ExportEntry
{
    /**
     * ユニットの独自データ
     * @var BlockEditorAttributes
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
        return 'block-editor';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('ブロックエディター');
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
     * エントリーのエクスポートでエクスポートするアセットを返却
     *
     * @return string[]
     */
    public function exportArchivesFiles(): array
    {
        return [];
    }

    /**
     * エントリーのエクスポートでエクスポートするメディアIDを返却
     *
     * @return int[]
     */
    public function exportMediaIds(): array
    {
        return BlockEditorHelper::extractMediaId($this->getField1());
    }

    /**
     * エントリーのエクスポートでエクスポートするモジュールIDを返却
     *
     * @inheritDoc
     */
    public function exportModuleId(): ?int
    {
        return null;
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
        $this->setField1($request["block-editor_html_{$id}"] ?? '');
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
        if ($html === '') {
            return '';
        }
        return $html;
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        $html = $this->getField1();
        return [strip_tags($html)];
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
        $html = BlockEditorHelper::fix($this->getField1(), true);
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
     * @inheritDoc
     */
    public function processConfig(array $config): array
    {
        $config['field_1'] = BlockEditorHelper::fix(html: $config['field_1'], resizeImage: false);
        return $config;
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'html' => BlockEditorHelper::fix($this->getField1(), true),
        ];
    }
}
