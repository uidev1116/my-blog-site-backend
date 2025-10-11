<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\ProcessExtender;
use Acms\Services\Unit\Contracts\EagerLoadingCustom;
use Acms\Services\Unit\Contracts\UnitListModule;
use Acms\Services\Unit\Contracts\ExportEntry;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\PublicStorage;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Traits\Unit\AnkerUnitTrait;
use Template;
use Field;
use ACMS_Validator;
use ACMS_Hook;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Custom extends Model implements UnitListModule, ExportEntry, ProcessExtender, EagerLoadingCustom, AlignableUnitInterface, AnkerUnitInterface
{
    use \Acms\Traits\Common\AssetsTrait;
    use AlignableUnitTrait;
    use AnkerUnitTrait;

    /**
     * ユニットの独自データ
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * カスタムユニットのカスタムフィールドを一時的に保持
     *
     * @var Field|null
     */
    private $tempField = null;

    /**
     * Eager Load されたカスタムユニットフィールドデータ
     *
     * @var array
     */
    private $eagerLoadedCustomUnitFields = [];

    /**
     * @inheritDoc
     */
    public function __clone()
    {
        $field = $this->getCustomUnitField();
        if ($field instanceof Field) {
            $this->tempField = clone $field;
        }
        parent::__clone(); // IDが更新されるので、必ず最後に呼び出すこと
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * カスタムフィールドを取得
     *
     * @return Field|null
     */
    public function getCustomUnitField(): ?Field
    {
        $id = $this->getId();
        $field = null;

        if ($this->tempField) {
            $field = $this->tempField;
        } elseif ($id && isset($this->eagerLoadedCustomUnitFields[$id])) {
            $field = $this->eagerLoadedCustomUnitFields[$id];
        } elseif ($id) {
            $field = loadUnitField($id, $this->getRevId());
        }
        if ($field instanceof Field) {
            $this->formatField($field);
            return $field;
        }
        return null;
    }

    /**
     * 事前読み込みされたカスタムフィールドマップを設定
     *
     * @param array $fields
     * @return void
     */
    public function setEagerLoadedCustomUnitFields(array $fields): void
    {
        $this->eagerLoadedCustomUnitFields = $fields;
    }

    /**
     * ユニットロード時に拡張処理を行います
     *
     * @return void
     */
    public function extendOnLoad(): void
    {
    }

    /**
     * ユニット保存時に拡張処理を行います
     *
     * @return void
     */
    public function extendOnSave(): void
    {
        $field = $this->getCustomUnitField();
        if ($field instanceof Field) {
            if ($id = $this->getId()) {
                Common::saveField('unit_id', $id, $field, null, $this->getRevId());
            }
        }
    }

    /**
     * エントリーのエクスポートでエクスポートするアセットを返却
     *
     * @return string[]
     */
    public function exportArchivesFiles(): array
    {
        $field = $this->getCustomUnitField();
        if (!$field instanceof Field) {
            return [];
        }
        $exportFiles = [];
        foreach ($field->listFields() as $fd) {
            foreach ($field->getArray($fd, true) as $i => $val) {
                if (empty($val)) {
                    continue;
                }
                if (
                    strpos($fd, '@path') ||
                    strpos($fd, '@tinyPath') ||
                    strpos($fd, '@largePath') ||
                    strpos($fd, '@squarePath')
                ) {
                    $exportFiles[] = $val;
                }
            }
        }
        return $exportFiles;
    }

    /**
     * エントリーのエクスポートでエクスポートするメディアIDを返却
     *
     * @return int[]
     */
    public function exportMediaIds(): array
    {
        $field = $this->getCustomUnitField();
        if (!$field instanceof Field) {
            return [];
        }
        $exportMediaIds = [];
        foreach ($field->listFields() as $fd) {
            foreach ($field->getArray($fd, true) as $i => $val) {
                if (empty($val)) {
                    continue;
                }
                if (strpos($fd, '@media') !== false) {
                    $mediaId = intval($val);
                    if ($mediaId > 0) {
                        $exportMediaIds[] = $mediaId;
                    }
                }
            }
        }
        return $exportMediaIds;
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
     * Unit_Listモジュールを描画
     *
     * @param Template $tpl
     * @return array
     */
    public function renderUnitListModule(Template $tpl): array
    {
        Common::setForceV1Build(true);
        $field = $this->getCustomUnitField();
        if (!$field instanceof Field) {
            return [];
        }
        $block = 'unit#' . $this->getType();
        $tpl->add([$block, 'unit:loop'], TemplateHelper::buildField($field, $tpl, [$block, 'unit:loop']));
        Common::setForceV1Build(false);

        return [];
    }

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        return 'custom';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('カスタム');
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
        $field = Common::extract('unit' . $id, new ACMS_Validator(), new Field());
        $field->retouchCustomUnit($id);
        $this->tempField = $field;
        $hook = HOOK_ENABLE ? ACMS_Hook::singleton() : null;

        if (Entry::isNewVersion()) {
            foreach ($field->listFields() as $fd) {
                if (
                    !strpos($fd, '@path') &&
                    !strpos($fd, '@tinyPath') &&
                    !strpos($fd, '@largePath') &&
                    !strpos($fd, '@squarePath')
                ) {
                    continue;
                }
                $set = false;
                foreach ($field->getArray($fd, true) as $old) {
                    if (in_array($old, Entry::getUploadedFiles(), true)) {
                        continue;
                    }
                    $info = pathinfo($old);
                    $dirname = empty($info['dirname']) ? '' : $info['dirname'] . '/';
                    PublicStorage::makeDirectory(ARCHIVES_DIR . $dirname);
                    $ext = empty($info['extension']) ? '' : '.' . $info['extension'];
                    $newOld = $dirname . uniqueString() . $ext;

                    $path = ARCHIVES_DIR . $old;
                    $newPath = ARCHIVES_DIR . $newOld;
                    copyFile($path, $newPath, true);
                    if ($hook) {
                        $hook->call('mediaCreate', $newPath);
                    }
                    if (!$set) {
                        $field->delete($fd);
                        $set = true;
                    }
                    $field->add($fd, $newOld);
                }
            }
            $this->tempField = $field;
        }
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        $field = $this->getCustomUnitField();
        if (!$field instanceof Field) {
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
        $field = $this->getCustomUnitField();
        if (!($field instanceof Field)) {
            return;
        }
        $this->duplicateFieldsTrait($field);
        if ($id = $this->getId()) {
            Common::saveField('unit_id', $id, $field, null, $this->getRevId());
        }
    }

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    public function handleRemove(): void
    {
        $field = $this->getCustomUnitField();
        if (!($field instanceof Field)) {
            return;
        }
        $this->removeFieldAssetsTrait($field);
    }

    /**
     * キーワード検索用のワードを取得
     *
     * @return string
     */
    public function getSearchText(): string
    {
        $text = '';
        $field = $this->getCustomUnitField();
        if (!($field instanceof Field)) {
            return '';
        }
        foreach ($field->listFields() as $f) {
            $search = $field->getMeta($f, 'search');
            if ($search) {
                $text .= implode(' ', $field->getArray($f)) . ' ';
            }
        }
        return $text;
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
        $field = $this->getCustomUnitField();
        if (!$field instanceof Field) {
            return;
        }
        $vars['align'] = $this->getAlign()->value;
        $vars['anker'] = $this->getAnker();
        TemplateHelper::injectMediaField($field, true);
        $block = array_merge(['unit#' . $this->getType()], $rootBlock);
        $vars += TemplateHelper::buildField($field, $tpl, $block, null, [
            'utid' => $this->getId(),
        ]);
        $tpl->add($block, $vars);
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
        $field = $this->getCustomUnitField();
        $block = array_merge([$this->getType()], $rootBlock);
        if ($field instanceof Field) {
            TemplateHelper::injectMediaField($field, true);
            TemplateHelper::injectRichEditorField($field, true);
            $vars += TemplateHelper::buildField($field, $tpl, $block, null, ['id' => $this->getId()]);
        }
        $tpl->add($block, $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        $field = $this->getCustomUnitField();
        return [
            'field' => acmsSerialize($field)
        ];
    }

    /**
     * フィールドを整形
     *
     * @param Field $field
     * @return void
     */
    protected function formatField(Field $field): void
    {
        foreach ($field->listFields() as $fd) {
            if (
                !strpos($fd, '@path') &&
                !strpos($fd, '@tinyPath') &&
                !strpos($fd, '@largePath') &&
                !strpos($fd, '@squarePath')
            ) {
                continue;
            }
            $set = false;
            foreach ($field->getArray($fd, true) as $i => $path) {
                if (!$set) {
                    $field->delete($fd);
                    $set = true;
                }
                $field->add($fd, (string) $path);
            }
        }
    }
}
