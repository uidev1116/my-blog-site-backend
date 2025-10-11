<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Category;
use Acms\Services\Facades\Template as Tpl;
use Acms\Services\Unit\UnitCollection;

class ACMS_GET_Admin_Entry_Edit extends ACMS_GET_Admin
{
    /**
     * @var string[]
     */
    public $fieldNames  =  [];

    /**
     * @var \Acms\Services\Unit\Repository
     */
    private $unitService;

    /**
     * @var \Acms\Services\Unit\Rendering\Edit
     */
    private $unitRenderingService;

    /**
     * @see ACMS_User_GET_EntryExtendSample_Edit
     *
     * @param string $fieldName
     * @param int $eid
     * @return Field
     */
    public function loadCustomField($fieldName, $eid)
    {
        $Field = new Field_Validation();
        return $Field;
    }

    public function get()
    {
        /** @var int|null $entryId */
        $entryId = EID;
        /** @var int|null $revisionId */
        $revisionId = RVID;
        /** @var int $blogId */
        $blogId = BID;
        /** @var int|null $categoryId */
        $categoryId = CID;
        $this->validatePermissions($entryId, $categoryId, $blogId, $revisionId);

        if (!$this->isEntryEditPage()) {
            return '';
        }

        if (!defined('IS_EDITING_ENTRY')) {
            define('IS_EDITING_ENTRY', true);
        }

        $this->unitService = Application::make('unit-repository');
        $this->unitRenderingService = Application::make('unit-rendering-edit');

        $CustomFieldCollection = [];
        /** @var \Acms\Services\Unit\UnitCollection|null $collection */
        $collection = Entry::getTempUnitData();
        $vars = [];

        if (
            !$this->Post->isNull() &&
            !$this->Post->isValidAll() &&
            $collection instanceof UnitCollection
        ) {
            // バリデーションエラーの場合
            [$Entry, $Field, $Geo] = $this->handleValidationError();
        } else {
            if ($entryId !== null) {
                // 既存エントリー
                [$Entry, $Field, $Geo, $collection] = $this->loadExistingEntry($entryId, $revisionId);
                if ($Entry === null) {
                    return '';
                }

                // カスタムフィールドの読み込み
                foreach ($this->fieldNames as $fieldName) {
                    $CustomFieldCollection[$fieldName] = $this->loadCustomField($fieldName, $entryId);
                }
            } else {
                // 新規エントリー
                [$Entry, $Field, $Geo, $collection, $newEntryVars] = $this->setupNewEntry();
                $vars += $newEntryVars;
            }
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars += $this->buildRootVars(
            $tpl,
            $Entry,
            $Field,
            $Geo,
            $collection,
            $CustomFieldCollection,
            $entryId,
        );

        $tpl->add(null, $vars);
        return $tpl->get();
    }

    /**
     * エントリー編集ページかどうかを判定する
     *
     * @return bool
     */
    private function isEntryEditPage(): bool
    {
        return in_array(ADMIN, ['entry-edit', 'entry_editor'], true);
    }

    /**
     * 権限チェックを実行する
     * @param int|null $entryId
     * @param int|null $categoryId
     * @param int $blogId
     * @param int|null $revisionId
     *
     * @return void
     */
    private function validatePermissions(
        ?int $entryId,
        ?int $categoryId,
        int $blogId,
        ?int $revisionId
    ): void {
        if (!sessionWithContribution($blogId) && $this->isEntryEditPage()) {
            die403();
        }
        if (
            $entryId !== null &&
            !Entry::canEditView($entryId, $blogId, $categoryId) &&
            $this->isEntryEditPage()
        ) {
            die403();
        }
    }

    /**
     * バリデーションエラー時の処理を行う
     *
     * @return array{\Field, \Field, \Field}
     */
    private function handleValidationError(): array
    {
        $Entry = $this->Post->getChild('entry');
        $Field = $this->Post->getChild('field');
        $Geo = $this->Post->getChild('geometry');

        $this->processRelatedEntries($Entry);
        $this->processSubCategories($Entry);

        return [$Entry, $Field, $Geo];
    }

    /**
     * 関連エントリーの処理を行う
     *
     * @param \Field $Entry
     * @return void
     */
    private function processRelatedEntries(\Field $Entry): void
    {
        $relateds = $Entry->getArray('related');
        $relatedTypes = $Entry->getArray('related_type');
        $Entry->deleteField('related');
        foreach ($relateds as $i => $related) {
            $type = $relatedTypes[$i];
            if ($type) {
                $Entry->addField('related_' . $type, $related);
            } else {
                $Entry->addField('related', $related);
            }
        }
    }

    /**
     * サブカテゴリーの処理を行う
     *
     * @param \Field $Entry
     * @return void
     */
    private function processSubCategories(\Field $Entry): void
    {
        /** @var int[] $subCategoryIds */
        $subCategoryIds = array_map('intval', array_map('trim', explode(',', $Entry->get('sub_category_id'))));
        if (count($subCategoryIds) > 0) {
            $subCategories = $this->findCategories($subCategoryIds);
            $entrySubCategoryIds = array_column($subCategories, 'id');
            $entrySubCategoryLabels = array_column($subCategories, 'label');
            $Entry->setField('sub_category_id', implode(',', $entrySubCategoryIds));
            $Entry->setField('sub_category_label', implode(',', $entrySubCategoryLabels));
        }
    }

    /**
     * 既存エントリーのデータを読み込む
     *
     * @param int $entryId
     * @param int|null $revisionId
     * @return array{Field_Validation|null, Field, Field, UnitCollection|null}
     */
    private function loadExistingEntry(int $entryId, ?int $revisionId): array
    {
        $Entry = new Field_Validation();
        $Field = new Field_Validation();
        $Geo = new Field_Validation();

        $entry = $this->findEntry($entryId, $revisionId);
        if ($entry === null) {
            return [null, $Field, $Geo, null];
        }

        if (is_null($revisionId) && $entry['entry_approval'] === 'pre_approval') {
            $revisionId = 1;
        }

        // カスタムフィールドの読み込み
        $Field = loadEntryField($entryId, $revisionId, true);

        // エントリー基本情報の設定
        $this->setEntryBasicFields($Entry, $entry);

        // ユニットの読み込み
        $collection = $this->unitService->loadUnits(
            eid: $entryId,
            rvid: $revisionId,
            options: ['setPrimaryImage' => true],
        );

        // その他の関連データ読み込み
        $this->loadEntryRelatedData($Entry, $entryId, $revisionId);
        $Geo = loadGeometry('eid', $entryId, $revisionId);

        return [$Entry, $Field, $Geo, $collection];
    }

    /**
     * 新規エントリーのデフォルト設定を行う
     *
     * @return array{Field_Validation, Field_Validation, Field_Validation, UnitCollection, array<string, string>}
     */
    private function setupNewEntry(): array
    {
        $Entry = new Field_Validation();
        $Field = new Field_Validation();
        $Geo = new Field_Validation();

        $collection = $this->unitService->loadDefaultUnit();

        $vars = [
            'category_id' => config('entry_edit_category_default', (string)CID),
            'status:selected#' . config('initial_entry_status', 'draft') => config('attr_selected'),
            'indexing:checked#' . config('entry_edit_indexing_default', 'on') => config('attr_checked'),
            'members_only:checked#' . config('entry_edit_members_only_default', 'off') => config('attr_checked'),
        ];

        return [$Entry, $Field, $Geo, $collection, $vars];
    }

    /**
     * エントリーの基本フィールドを設定する
     *
     * @param Field_Validation $Entry
     * @param array<string, mixed> $entry
     * @return void
     */
    private function setEntryBasicFields(Field_Validation $Entry, array $entry): void
    {
        $Entry->setField('status', $entry['entry_status']);
        $Entry->setField('title', $entry['entry_title']);
        $Entry->setField('code', $entry['entry_code']);
        $Entry->setField('link', $entry['entry_link']);
        $Entry->setField('indexing', $entry['entry_indexing']);
        $Entry->setField('members_only', $entry['entry_members_only']);
        $Entry->setField('summary_range', $entry['entry_summary_range']);
        $Entry->setField('category_id', $entry['entry_category_id']);
        $Entry->setField('primary_image', $entry['entry_primary_image']);

        $this->setEntryDateFields($Entry, $entry);
    }

    /**
     * エントリーの日時フィールドを設定する
     *
     * @param Field_Validation $Entry
     * @param array<string, mixed> $entry
     * @return void
     */
    private function setEntryDateFields(Field_Validation $Entry, array $entry): void
    {
        list($date, $time) = explode(' ', $entry['entry_datetime']);
        $Entry->setField('date', $date);
        $Entry->setField('time', $time);

        list($date, $time) = explode(' ', $entry['entry_start_datetime']);
        $Entry->setField('start_date', $date);
        $Entry->setField('start_time', $time);

        list($date, $time) = explode(' ', $entry['entry_end_datetime']);
        $Entry->setField('end_date', $date);
        $Entry->setField('end_time', $time);
    }

    /**
     * エントリーの関連データを読み込む
     *
     * @param Field_Validation $Entry
     * @param int $entryId
     * @param int|null $revisionId
     * @return void
     */
    private function loadEntryRelatedData(Field_Validation $Entry, int $entryId, ?int $revisionId): void
    {
        // タグの読み込み
        $this->loadEntryTags($Entry, $entryId, $revisionId);

        // サブカテゴリーの読み込み
        $this->loadEntrySubCategories($Entry, $entryId, $revisionId);

        // 関連エントリーの読み込み
        $this->loadEntryRelatedEntries($Entry, $entryId, $revisionId);
    }

    /**
     * エントリーのタグを読み込む
     *
     * @param Field_Validation $Entry
     * @param int $entryId
     * @param int|null $revisionId
     * @return void
     */
    private function loadEntryTags(Field_Validation $Entry, int $entryId, ?int $revisionId): void
    {
        $tags = $this->findEntryTags($entryId, $revisionId);
        $Entry->setField('tag', implode(', ', $tags));
    }

    /**
     * エントリーのサブカテゴリーを読み込む
     *
     * @param Field_Validation $Entry
     * @param int $entryId
     * @param int|null $revisionId
     * @return void
     */
    private function loadEntrySubCategories(Field_Validation $Entry, int $entryId, ?int $revisionId): void
    {
        $subCategories = loadSubCategories($entryId, $revisionId);
        $subCategoryIds = $subCategories['id'];
        $subCategoryLabels = $subCategories['label'];
        if (count($subCategoryIds) > 0) {
            $Entry->addField('sub_category_id', implode(',', $subCategoryIds));
            $Entry->addField('sub_category_label', implode(',', $subCategoryLabels));
        }
    }

    /**
     * エントリーの関連エントリーを読み込む
     *
     * @param Field_Validation $Entry
     * @param int $entryId
     * @param int|null $revisionId
     * @return void
     */
    private function loadEntryRelatedEntries(Field_Validation $Entry, int $entryId, ?int $revisionId): void
    {
        // 通常の関連エントリー
        if ($relatedEids = loadRelatedEntries($entryId, $revisionId)) {
            foreach ($relatedEids as $reid) {
                $Entry->addField('related', $reid);
            }
        }

        // 関連エントリーグループ
        foreach (configArray('related_entry_type') as $type) {
            $relatedEids = loadRelatedEntries($entryId, $revisionId, $type);
            foreach ($relatedEids as $reid) {
                $Entry->addField('related_' . $type, $reid);
            }
        }
    }

    /**
     * ルート変数を構築する
     *
     * @param \Template $tpl
     * @param \Field $Entry
     * @param \Field $Field
     * @param \Field $Geo
     * @param UnitCollection|null $collection
     * @param array<string, \Field> $CustomFieldCollection
     * @param int|null $entryId
     * @return array<string, mixed>
     */
    private function buildRootVars(
        \Template $tpl,
        \Field $Entry,
        \Field $Field,
        \Field $Geo,
        ?UnitCollection $collection,
        array $CustomFieldCollection,
        ?int $entryId
    ): array {
        $vars = [];

        // ユニット関連
        if ($collection !== null) {
            $vars += $this->unitRenderingService->render($collection, $tpl, []);
        }

        // 関連エントリー
        $vars += $this->buildRelatedEntryVars($tpl, $Entry);

        // Next EID
        if ($entryId === null) {
            $vars['next_eid'] = intval(DB::query(SQL::currval('entry_id', dsn()), 'one')) + 1;
        }

        // カテゴリー作成可能か
        $vars['category_creatable'] = Category::canCreate(BID) ? 'true' : 'false';

        // フィールド構築
        $vars += Tpl::buildField($Entry, $tpl, [], 'entry');
        $vars += Tpl::buildField($Field, $tpl, [], 'field');
        $vars += Tpl::buildField($Geo, $tpl, [], 'geometry');

        // カスタムフィールド
        foreach ($CustomFieldCollection as $fieldName => $customField) {
            $vars += Tpl::buildField($customField, $tpl, [], $fieldName);
        }

        return $vars;
    }

    /**
     * 関連エントリーのテンプレート変数を構築する
     *
     * @param \Template $tpl
     * @param \Field $Entry
     * @return array<string, mixed>
     */
    private function buildRelatedEntryVars(\Template $tpl, \Field $Entry): array
    {
        $vars = [];

        // 関連エントリーの設定値
        $vars['related_entry_first_label'] = config('related_entry_first_label');
        $vars['related_entry_first_module_id'] = config('related_entry_first_module_id');
        $vars['related_entry_first_ctx'] = config('related_entry_first_ctx');
        $vars['related_entry_first_max_item'] = config('related_entry_first_max_item');
        $vars['related_entry_first_thumbnail_field'] = config('related_entry_first_thumbnail_field');

        // 通常の関連エントリー
        if ($relatedEids = $Entry->getArray('related')) {
            $Entry->delete('related');
            Tpl::buildRelatedEntries($tpl, $relatedEids, [], $this->start, $this->end, 'related:loop', config('related_entry_first_thumbnail_field', config('main_image_field_name')));
        }

        // 関連エントリーグループ
        foreach (configArray('related_entry_type') as $i => $type) {
            if ($type === '') {
                continue;
            }
            $relatedEids = $Entry->getArray('related_' . $type);
            $label = config('related_entry_label', '', $i);
            $moduleId = config('related_entry_module_id', '', $i);
            $ctx = config('related_entry_ctx', '', $i);
            $maxItem = config('related_entry_max_item', '', $i);
            $thumbnailField = config('related_entry_thumbnail_field', '', $i);
            if (!!$relatedEids) {
                $Entry->delete('related_' . $type);
                Tpl::buildRelatedEntries($tpl, $relatedEids, ['related_group:loop'], $this->start, $this->end, 'other_related:loop', $thumbnailField);
            }
            $tpl->add(['related_group:loop'], [
                'related_label' => $label,
                'related_type' => $type,
                'related_module_id' => $moduleId,
                'related_ctx' => $ctx,
                'related_thumbnail_field' => $thumbnailField,
                'related_max_item' => $maxItem,
            ]);
        }

        return $vars;
    }

    /**
     * @param int[] $categoryIds
     * @return array{
     *  id: int,
     *  label: string
     * }[]
     */
    protected function findCategories(array $categoryIds): array
    {
        $categories = [];
        $sql = SQL::newSelect('category');
        $sql->addWhereIn('category_id', $categoryIds);
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');
        if ($statement) {
            while ($row = DB::next($statement)) {
                $categories[] = [
                    'id' => (int)$row['category_id'],
                    'label' => (string)$row['category_name']
                ];
            }
        }
        return $categories;
    }

    /**
     * エントリーのデータを取得する
     *
     * @param int $entryId
     * @param int|null $revisionId
     * @return array<string, mixed>|null
     */
    protected function findEntry(int $entryId, ?int $revisionId): ?array
    {
        if ($revisionId !== null && $revisionId > 0) {
            $sql = SQL::newSelect('entry_rev');
            $sql->addWhereOpr('entry_id', $entryId);
            $sql->addWhereOpr('entry_blog_id', BID);
            $sql->addWhereOpr('entry_rev_id', $revisionId);
            $query = $sql->get(dsn());
            /** @var array|false $row */
            $row = DB::query($query, 'row');
            if ($row === false) {
                return null;
            }
            return $row;
        }
        $row = ACMS_RAM::entry($entryId);
        return $row;
    }

    /**
     * @param int $entryId
     * @param int|null $revisionId
     * @return string[]
     */
    protected function findEntryTags(int $entryId, ?int $revisionId): array
    {
        $tags = [];
        if ($revisionId !== null && $revisionId > 0) {
            $sql = SQL::newSelect('tag_rev');
            $sql->addWhereOpr('tag_rev_id', $revisionId);
        } else {
            $sql = SQL::newSelect('tag');
        }
        $sql->setSelect('tag_name');
        $sql->addWhereOpr('tag_entry_id', $entryId);
        $sql->addOrder('tag_sort', 'ASC');
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');
        if ($statement) {
            while ($row = DB::next($statement)) {
                $tags[] = (string)$row['tag_name'];
            }
        }
        return $tags;
    }
}
