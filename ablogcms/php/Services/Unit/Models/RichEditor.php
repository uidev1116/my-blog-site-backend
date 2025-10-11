<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Facades\RichEditor as RichEditorHelper;
use Acms\Services\Unit\Contracts\ConfigProcessable;
use Template;

/**
 * @phpstan-type RichEditorAttributes array{json: string}
 * @extends \Acms\Services\Unit\Contracts\Model<RichEditorAttributes>
 */
class RichEditor extends Model implements ConfigProcessable
{
    /**
     * ユニットの独自データ
     * @var RichEditorAttributes
     */
    private $attributes = [
        'json' => '',
    ];

    /**
     * ユニットタイプを取得
     *
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'rich-editor';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('リッチエディター') . gettext('（非推奨）');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            ...$this->attributes,
            'json' => $this->getField1(),
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
        $this->setField1($request["rich-editor_json_{$id}"] ?? '');
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if (strip_tags($this->getField1()) === '') {
            // タグを削除した状態で空文字列になった場合は保存できない
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
        $json = $this->getField1();
        if ($json === '') {
            return '';
        }
        return RichEditorHelper::render($json);
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        $json = $this->getField1();
        if ($json === '') {
            return [];
        }
        $html = RichEditorHelper::render($json);
        $text = strip_tags($html);
        return [$text];
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
        $json = $this->getField1();
        if ($json === '') {
            return;
        }
        $vars = [
            'html' => RichEditorHelper::render($json),
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
        $json = json_encode([
            'html' => RichEditorHelper::render($config['field_1']),
            'title' => RichEditorHelper::renderTitle($config['field_1']),
        ]);
        $config['field_1'] = $json ? $json : '{}';
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
            'json' => $this->getField1()
        ];
    }
}
