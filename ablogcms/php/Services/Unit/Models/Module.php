<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Services\Unit\Contracts\ExportEntry;
use Acms\Services\Facades\Template as TemplateHelper;
use Template;

/**
 * @phpstan-type ModuleAttributes array{mid: int|null, tpl: string}
 * @extends \Acms\Services\Unit\Contracts\Model<ModuleAttributes>
 */
class Module extends Model implements AlignableUnitInterface, ExportEntry
{
    use AlignableUnitTrait;

    /**
     * ユニットの独自データ
     * @var ModuleAttributes
     */
    private $attributes = [
        'mid' => null,
        'tpl' => '',
    ];

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            ...$this->attributes,
            'mid' => $this->getField1() !== '' ? (int) $this->getField1() : null,
            'tpl' => $this->getField2(),
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
        return [];
    }

    /**
     * エントリーのエクスポートでエクスポートするモジュールIDを返却
     *
     * @inheritDoc
     */
    public function exportModuleId(): ?int
    {
        $moduleId = (int) $this->getField1();
        if ($moduleId <= 0) {
            return null;
        }
        return $moduleId;
    }

    /**
     * ユニットタイプを取得
     *
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'module';
    }

    /**
     * ユニットラベルを取得
     *
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('モジュール');
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
        $this->setField1($request["module_mid_{$id}"] ?? '');
        $this->setField2($request["module_tpl_{$id}"] ?? '');
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
        $mid = (int) $this->getField1();
        if ($mid === 0) {
            return;
        }
        $template = $this->getField2();
        $module = loadModule($mid);
        $name = $module->get('name');
        $identifier = $module->get('identifier');
        $vars['view'] = TemplateHelper::spreadModule($name, $identifier, $template);
        $vars['align'] = $this->getAlign()->value;

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
            'mid' => $this->getField1(),
            'tpl' => $this->getField2()
        ];
    }
}
