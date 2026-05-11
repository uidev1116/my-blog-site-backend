<?php

use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Application;
use Acms\Services\Entry\Exceptions\TagValidationException;
use Acms\Services\Entry\Exceptions\SubCategoryValidationException;

/**
 * エントリーCSVインポート用モデルクラス
 *
 * エントリーのCSVインポート処理を実装するクラス
 */
class ACMS_POST_Import_Model_Entry extends ACMS_POST_Import_Model
{
    use \Acms\Traits\Unit\UnitModelTrait;
    use \Acms\Traits\CsvImport\UpdateByFieldKey;

    /** @var array<string, mixed> エントリーデータ */
    protected array $entry = [];

    /** @var array<int, array<string, mixed>> ユニットデータ配列 */
    protected array $units = [];

    /** @var int インポート先ブログID */
    protected int $importBid = BID;

    /** @var array<int> サブカテゴリーID配列 */
    protected array $subCategories = [];

    /** @var float 緯度 */
    protected float $geoLat = 0.0;

    /** @var float 経度 */
    protected float $geoLng = 0.0;

    /** @var int 地図のズームレベル */
    protected int $geoZoom = 11;

    /** @var array<int, string> タグ配列 */
    protected array $tags = [];

    /** @var string IDラベル名 */
    protected string $idLabel = 'entry_id';

    /**
     * インポート先カテゴリーIDを設定
     *
     * @param int|null $cid カテゴリーID
     * @return void
     */
    public function setTargetCid(?int $cid): void
    {
        $this->importCid = $cid;
    }

    /**
     * インポート先ブログIDを設定
     *
     * @param int $bid ブログID
     * @return void
     */
    public function setTargetBid(int $bid): void
    {
        $this->importBid = $bid;
    }

    /**
     * エントリーの存在チェック
     *
     * CSVから取得したIDが存在し、更新可能な状態かを確認する
     *
     * @return bool 存在し更新可能な場合true
     */
    protected function exist(): bool
    {
        if (is_null($this->csvId)) {
            return false;
        }
        $entryBlogId = ACMS_RAM::entryBlog($this->csvId);
        $entryCode = ACMS_RAM::entryCode($this->csvId);
        $entryStatus = ACMS_RAM::entryStatus($this->csvId);
        if ($entryBlogId !== BID) {
            // 実行ブログに存在しないエントリーは更新できない
            return false;
        }
        if ($entryCode === null) {
            // コードが null = 存在しないエントリー
            return false;
        }
        if ($entryStatus === null || $entryStatus === '' || $entryStatus === 'trash') {
            // ステータスが存在しない、または削除されたエントリーは更新できない
            return false;
        }
        return true;
    }

    /**
     * 次発行されるエントリーIDを設定
     *
     * @return void
     */
    protected function nextId(): void
    {
        $this->nextId = intval(DB::query(SQL::nextval('entry_id', dsn()), 'seq'));
    }

    /**
     * 保存処理
     *
     * 更新キー処理、データ組み立てを行い、更新または挿入を実行する
     * バリデーションは save() で実行（setTargetCid() や setTargetBid() の後に実行される）
     *
     * @return void
     * @throws RuntimeException 重複キーエラー時
     */
    public function save(): void
    {
        // 完全なバリデーションを実行（参照整合性チェックなど）
        $this->validate();

        $this->updateKey();
        $this->build();

        if ($this->isUpdate) {
            $this->update();
        } else {
            $this->insert();
        }
    }

    /**
     * バリデーション
     *
     * save() メソッドで実行される完全なバリデーション
     * 必須フィールドの存在確認、データフォーマットの検証、重複チェックを実行する
     *
     * @return void
     * @throws RuntimeException バリデーションエラー時
     */
    protected function validate(): void
    {
        // 1. 基本的なフォーマットチェック（既存の formatCheck の内容）
        $this->validateBasicFormat();

        // 2. 文字数制限チェック
        $this->validateFieldLengths();

        // 3. エントリーコードのバリデーション
        $this->validateEntryCode();

        // 4. カテゴリー・ユーザーの存在チェック
        $this->validateReferences();

        // 5. タグ・サブカテゴリーのバリデーション
        $this->validateTagsAndSubCategories();

        // 6. 日時の妥当性チェック
        $this->validateDateTimes();
    }

    /**
     * 基本的なフォーマットチェック
     *
     * validate() 内で実行される基本的なフォーマットチェック
     *
     * @return void
     * @throws RuntimeException フォーマットが不正な場合
     */
    private function validateBasicFormat(): void
    {
        foreach ($this->data as $key => $value) {
            switch ($key) {
                case 'entry_id':
                    // 新規作成の場合は空文字列が許可される
                    if ($value !== '' && !is_numeric($value)) {
                        throw new \RuntimeException('数値でない値が設定されています（' . $key . '）。入力された値: ' . $value);
                    }
                    break;
                case 'entry_summary_range':
                case 'entry_category_id':
                case 'entry_user_id':
                    if ($value !== '' && !is_numeric($value)) {
                        throw new \RuntimeException('数値でない値が設定されています（' . $key . '）。入力された値: ' . $value);
                    }
                    break;
                case 'entry_status':
                    if (!in_array($value, ['open', 'close', 'draft', 'trash'], true)) {
                        throw new \RuntimeException('不正な値が設定されています（' . $key . '）。open, close, draft, trash のいずれかを指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'entry_datetime':
                case 'entry_updated_datetime':
                case 'entry_posted_datetime':
                    if ($value !== '' && !preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                        throw new \RuntimeException('日時のフォーマットが間違っています（' . $key . '）。YYYY-MM-DD HH:MM:SS 形式で指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'entry_start_datetime':
                case 'entry_end_datetime':
                    // 空文字列の場合はスキップ（オプショナルフィールド）
                    if ($value !== '' && !preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                        throw new \RuntimeException('日時のフォーマットが間違っています（' . $key . '）。YYYY-MM-DD HH:MM:SS 形式で指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'entry_indexing':
                case 'entry_members_only':
                    // 空文字列の場合はスキップ（デフォルト値が使用される）
                    if ($value !== '' && !in_array($value, ['on', 'off'], true)) {
                        throw new \RuntimeException('on または off 以外の値が設定されています（' . $key . '）。入力された値: ' . $value);
                    }
                    break;
            }
        }
    }

    /**
     * 文字数制限チェック
     *
     * @return void
     * @throws RuntimeException 文字数制限を超えている場合
     */
    private function validateFieldLengths(): void
    {
        // entry_title: varchar(255)
        if (isset($this->data['entry_title']) && $this->data['entry_title'] !== '') {
            $length = mb_strlen($this->data['entry_title'], 'UTF-8');
            if ($length > 255) {
                throw new \RuntimeException('タイトルが長すぎます（entry_title）。最大255文字まで入力できます。現在: ' . $length . '文字。入力された値: ' . $this->data['entry_title']);
            }
        }

        // entry_code: varchar(64) - 拡張子をつけた後の文字数でチェック
        if (isset($this->data['entry_code']) && $this->data['entry_code'] !== '') {
            $code = $this->data['entry_code'];
            $originalCode = $code;
            // 拡張子が含まれていない場合は拡張子を追加
            $code = Entry::formatEntryCode($code);
            $length = mb_strlen($code, 'UTF-8');
            if ($length > 64) {
                throw new \RuntimeException('エントリーコードが長すぎます（entry_code）。最大64文字まで入力できます（拡張子を含む）。現在: ' . $length . '文字。入力された値: ' . $originalCode);
            }
        }

        // entry_link: varchar(255)
        if (isset($this->data['entry_link']) && $this->data['entry_link'] !== '') {
            $length = mb_strlen($this->data['entry_link'], 'UTF-8');
            if ($length > 255) {
                throw new \RuntimeException('リンクが長すぎます（entry_link）。最大255文字まで入力できます。現在: ' . $length . '文字。入力された値: ' . $this->data['entry_link']);
            }
        }
    }

    /**
     * エントリーコードのバリデーション
     *
     * @return void
     * @throws RuntimeException エントリーコードが不正な場合
     */
    private function validateEntryCode(): void
    {
        if (!isset($this->data['entry_code'])) {
            return; // エントリーコードが指定されていない場合はスキップ（自動生成される）
        }

        $code = $this->data['entry_code'];

        if ($code !== '') {
            // 拡張子が含まれていない場合は拡張子を追加してチェック
            $code = Entry::formatEntryCode($code);
        }

        // 形式チェック
        if ($code !== '' && !isValidCode($code)) {
            $originalCode = $this->data['entry_code'];
            throw new \RuntimeException('エントリーコードの形式が正しくありません（entry_code）。改行、タブ、制御文字、引用符を含むことはできません。入力された値: ' . $originalCode);
        }

        // 予約語チェック（拡張子を追加した後に行う）
        if (isReserved($code, false)) {
            throw new \RuntimeException('エントリーコードに予約語が使用されています（entry_code: ' . $code . '）。別のコードを指定してください。');
        }

        // 重複チェック
        if (config('check_duplicate_entry_code') === 'on') {
            $categoryId = isset($this->data['entry_category_id']) && is_numeric($this->data['entry_category_id'])
                ? intval($this->data['entry_category_id'])
                : $this->importCid;
            $entryId = $this->isUpdate ? $this->csvId : null;
            if (Entry::validEntryCodeDouble($code, $this->importBid, $categoryId, $entryId)) {
                throw new \RuntimeException('エントリーコードが重複しています（entry_code: ' . $code . '）。別のコードを指定してください。');
            }
        }
    }

    /**
     * カテゴリー・ユーザーの存在チェック
     *
     * @return void
     * @throws RuntimeException 参照先が存在しない場合
     */
    private function validateReferences(): void
    {
        // カテゴリーIDの存在チェック
        if (isset($this->data['entry_category_id']) && $this->data['entry_category_id'] !== '') {
            $categoryId = intval($this->data['entry_category_id']);
            if ($categoryId > 0) {
                $categoryRepository = Application::make('category.repository');
                assert($categoryRepository instanceof \Acms\Services\Category\CategoryRepository);
                if (!$categoryRepository->exists($categoryId, $this->importBid)) {
                    throw new \RuntimeException('指定されたカテゴリーIDが存在しないか、現在のブログに属していません（entry_category_id: ' . $categoryId . '）。入力された値: ' . $this->data['entry_category_id']);
                }
            }
        }

        // ユーザーIDの存在チェック
        if (isset($this->data['entry_user_id']) && $this->data['entry_user_id'] !== '') {
            $userId = intval($this->data['entry_user_id']);
            if ($userId > 0) {
                $userRepository = Application::make('user.repository');
                assert($userRepository instanceof \Acms\Services\User\UserRepository);
                if (!$userRepository->exists($userId)) {
                    throw new \RuntimeException('指定されたユーザーIDが存在しません（entry_user_id: ' . $userId . '）。入力された値: ' . $this->data['entry_user_id']);
                }
            }
        }
    }

    /**
     * タグ・サブカテゴリーのバリデーション
     *
     * @return void
     * @throws TagValidationException タグのバリデーションエラーがある場合
     * @throws SubCategoryValidationException サブカテゴリーのバリデーションエラーがある場合
     */
    private function validateTagsAndSubCategories(): void
    {
        // タグのバリデーション（Entry::Helper のメソッドを使用）
        if (isset($this->data['entry_tag']) && $this->data['entry_tag'] !== '') {
            $tags = Common::getTagsFromString($this->data['entry_tag'], false);
            if (count($tags) > 0) {
                Entry::validateTagNames($tags);
            }
        }

        // サブカテゴリーのバリデーション（Entry::Helper のメソッドを使用）
        if (isset($this->data['entry_sub_category']) && $this->data['entry_sub_category'] !== '') {
            $subCategoryIds = Entry::getSubCategoryFromString($this->data['entry_sub_category'], ',');
            if (count($subCategoryIds) > 0) {
                Entry::validateSubCategoryIds($subCategoryIds);
            }
        }
    }

    /**
     * 日時の妥当性チェック
     *
     * @return void
     * @throws RuntimeException 日時が不正な場合
     */
    private function validateDateTimes(): void
    {
        $startDatetime = isset($this->data['entry_start_datetime']) ? $this->data['entry_start_datetime'] : null;
        $endDatetime = isset($this->data['entry_end_datetime']) ? $this->data['entry_end_datetime'] : null;

        if ($startDatetime !== null && $startDatetime !== '' && $endDatetime !== null && $endDatetime !== '') {
            $startTimestamp = strtotime($startDatetime);
            $endTimestamp = strtotime($endDatetime);
            if ($startTimestamp === false) {
                throw new \RuntimeException('開始日時が不正です（entry_start_datetime）。入力された値: ' . $startDatetime);
            }
            if ($endTimestamp === false) {
                throw new \RuntimeException('終了日時が不正です（entry_end_datetime）。入力された値: ' . $endDatetime);
            }
            if ($startTimestamp > $endTimestamp) {
                throw new \RuntimeException('開始日時が終了日時より後になっています（entry_start_datetime, entry_end_datetime）。開始日時は終了日時より前である必要があります。開始日時: ' . $startDatetime . ', 終了日時: ' . $endDatetime);
            }
        }
    }

    /**
     * フィールドIDカラム名を返す
     *
     * @return string
     */
    protected function getFieldIdColumn(): string
    {
        return 'field_eid';
    }

    /**
     * カスタムフィールドキーで特定したIDを適用する
     *
     * @param int $id
     * @return void
     */
    protected function applyFoundId(int $id): void
    {
        $this->csvId = $id;
        $this->isUpdate = true;
    }

    /**
     * updateKeyの検索対象から除外するラベルか判定する
     *
     * @param string $key
     * @return bool
     */
    protected function shouldSkipUpdateKeyLabel(string $key): bool
    {
        return strpos($key, 'unit@') === 0;
    }

    /**
     * エントリーデータの挿入
     *
     * エントリー本体、サブカテゴリー、タグ、位置情報、ユニット、フィールドを挿入する
     *
     * @return void
     */
    protected function insert(): void
    {
        $this->_insertEntry();
        $this->_insertSubCategory($this->nextId);
        $this->_insertTag($this->nextId);
        $this->_insertGeo($this->nextId);
        $this->_insertUnit();
        $this->_insertField();
        Common::saveFulltext('eid', $this->nextId, Common::loadEntryFulltext($this->nextId));
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$this->nextId, 0]);
        }
    }

    /**
     * エントリーデータの更新
     *
     * エントリー本体、サブカテゴリー、タグ、位置情報、ユニット、フィールドを更新する
     *
     * @return void
     * @throws RuntimeException エントリーが見つからない場合
     */
    protected function update(): void
    {
        if ($this->csvId === null) {
            throw new RuntimeException('更新対象のエントリーIDが設定されていません。');
        }
        $eid = $this->csvId;
        $this->_updateEntry($eid);
        $this->_insertSubCategory($eid);
        $this->_insertTag($eid);
        $this->_insertGeo($eid);
        $this->_updateUnit();
        $this->_updateField();
        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid), ACMS_RAM::entryBlog($eid));
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, 0]);
        }
    }

    /**
     * エントリー本体を挿入
     *
     * @return void
     */
    private function _insertEntry(): void
    {

        $sql = SQL::newInsert('entry');
        foreach ($this->entry as $key => $val) {
            $sql->addInsert($key, $val);
        }
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * エントリー本体を更新
     *
     * @param int $eid エントリーID
     * @return void
     * @throws RuntimeException エントリーが見つからない場合
     */
    private function _updateEntry(int $eid): void
    {

        if (!ACMS_RAM::entryStatus($eid)) {
            throw new RuntimeException('Not Found Entry.');
        }

        unset(
            $this->entry['entry_id'],
            $this->entry['entry_sort'],
            $this->entry['entry_user_sort'],
            $this->entry['entry_category_sort'],
            $this->entry['entry_hash'],
            $this->entry['entry_blog_id']
        );

        if (count($this->entry) > 0) {
            $sql = SQL::newUpdate('entry');
            foreach ($this->entry as $key => $val) {
                $sql->addUpdate($key, $val);
            }
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_blog_id', $this->importBid);
            DB::query($sql->get(dsn()), 'exec');
            ACMS_RAM::entry($eid, null);
        }
    }

    /**
     * サブカテゴリーを挿入
     *
     * @param int $eid エントリーID
     * @return void
     */
    private function _insertSubCategory(int $eid): void
    {
        if (count($this->subCategories) === 0) {
            return;
        }
        $deleteSql = SQL::newDelete('entry_sub_category');
        $deleteSql->addWhereOpr('entry_sub_category_eid', $eid);
        DB::query($deleteSql->get(dsn()), 'exec');

        $insertSql = SQL::newBulkInsert('entry_sub_category');
        foreach ($this->subCategories as $cid) {
            $insertSql->addInsert([
                'entry_sub_category_eid' => $eid,
                'entry_sub_category_id' => $cid,
                'entry_sub_category_blog_id' => $this->importBid,
            ]);
        }
        if ($insertSql->hasData()) {
            DB::query($insertSql->get(dsn()), 'exec');
        }
    }

    /**
     * タグを挿入
     *
     * @param int $eid エントリーID
     * @return void
     */
    private function _insertTag(int $eid): void
    {
        if (count($this->tags) === 0) {
            return;
        }
        $deleteSql = SQL::newDelete('tag');
        $deleteSql->addWhereOpr('tag_entry_id', $eid);
        DB::query($deleteSql->get(dsn()), 'exec');

        $insertSql = SQL::newBulkInsert('tag');
        foreach ($this->tags as $sort => $tag) {
            $insertSql->addInsert([
                'tag_name' => $tag,
                'tag_sort' => $sort + 1,
                'tag_entry_id' => $eid,
                'tag_blog_id' => $this->importBid,
            ]);
        }
        if ($insertSql->hasData()) {
            DB::query($insertSql->get(dsn()), 'exec');
        }
    }

    /**
     * 位置情報を挿入
     *
     * @param int $eid エントリーID
     * @return void
     */
    private function _insertGeo(int $eid): void
    {
        if ($this->geoLat === 0.0 || $this->geoLng === 0.0) {
            return;
        }
        $deleteSql = SQL::newDelete('geo');
        $deleteSql->addWhereOpr('geo_eid', $eid);
        DB::query($deleteSql->get(dsn()), 'exec');

        $insertSql = SQL::newInsert('geo');
        $insertSql->addInsert('geo_geometry', SQL::newGeometry($this->geoLat, $this->geoLng));
        $insertSql->addInsert('geo_zoom', intval($this->geoZoom));
        $insertSql->addInsert('geo_eid', $eid);
        $insertSql->addInsert('geo_blog_id', $this->importBid);
        DB::query($insertSql->get(dsn()), 'exec');
    }

    /**
     * ユニットを挿入
     *
     * @return void
     */
    private function _insertUnit(): void
    {
        if (count($this->units) > 0) {
            $sql = SQL::newBulkInsert('column');
            foreach ($this->units as $cval) {
                $cval['column_id'] = $this->generateNewIdTrait();
                $sql->addInsert($cval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * ユニットを更新
     *
     * @return void
     */
    private function _updateUnit(): void
    {
        if ($this->csvId === null) {
            return;
        }
        $eid = $this->csvId;

        if (count($this->units) > 0) {
            $deleteSql = SQL::newDelete('column');
            $deleteSql->addWhereOpr('column_entry_id', $eid);
            $deleteSql->addWhereIn('column_type', ['text', 'block-editor']);
            DB::query($deleteSql->get(dsn()), 'exec');

            $insertSql = SQL::newBulkInsert('column');
            foreach ($this->units as $cval) {
                $cval['column_id'] = $this->generateNewIdTrait();
                $cval['column_entry_id'] = $eid;
                $insertSql->addInsert($cval);
            }
            if ($insertSql->hasData()) {
                DB::query($insertSql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * フィールドを挿入
     *
     * @return void
     */
    private function _insertField(): void
    {
        $eid = $this->nextId;

        if (count($this->fields) > 0) {
            Common::deleteField('eid', $eid);

            $sql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $sql->addInsert($fval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * フィールドを更新
     *
     * @return void
     */
    private function _updateField(): void
    {
        if ($this->csvId === null) {
            return;
        }
        $eid = $this->csvId;

        if (count($this->fields) > 0) {
            $fkey = [];
            $deleteSql = SQL::newDelete('field');
            $deleteSql->addWhereOpr('field_eid', $eid);
            foreach ($this->fields as $dval) {
                foreach ($dval as $key => $val) {
                    if ($key === 'field_key') {
                        $fkey[] = $val;
                    }
                }
            }
            $deleteSql->addWhereIn('field_key', $fkey);
            DB::query($deleteSql->get(dsn()), 'exec');
            Common::deleteFieldCache('eid', $eid);

            $insertSql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $fval['field_eid'] = $eid;
                $fval['field_blog_id'] = ACMS_RAM::entryBlog($eid);
                $insertSql->addInsert($fval);
            }
            if ($insertSql->hasData()) {
                DB::query($insertSql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * 投稿日時を取得
     *
     * 現在日時にランダムな秒数を設定した日時を返す
     *
     * @return string 日時文字列（Y-m-d H:i:s形式）
     */
    private function getPostedDatetime(): string
    {
        $posted_datetime = date('Y-m-d H:i:s');
        $second = sprintf('%02d', rand(1, 59));
        $result = preg_replace('@[0-9]{2}$@', $second, $posted_datetime);
        if ($result === null) {
            return $posted_datetime;
        }

        return $result;
    }

    /**
     * エントリーデータの組み立て
     *
     * CSVデータからエントリー、ユニット、フィールドのデータを組み立てる
     *
     * @return void
     */
    protected function build(): void
    {
        $this->entry = $this->entryBase();
        $unit = $this->unitBase();
        $field = $this->fieldBase();

        foreach ($this->data as $key => $value) {
            if ($key === 'entry_id' && $this->isUpdate) {
                $this->entry['entry_id'] = $this->csvId;
                $unit['column_entry_id'] = $this->csvId;
                $field['field_eid'] = $this->csvId;
            }
            if (array_key_exists($key, $this->entry)) {
                $this->buildEntry($key, $value);
            } elseif ($key === 'entry_sub_category') {
                $this->buildSubCategory($value);
            } elseif ($key === 'entry_tag') {
                $this->buildTag($value);
            } elseif (strpos($key, 'unit@') === 0) {
                $this->buildUnit($unit, $key, $value);
            } elseif (in_array($key, ['geo_lat', 'geo_lng', 'geo_zoom'], true)) {
                $this->buildGeo($key, $value);
            } else {
                $this->buildField($field, $key, $value);
            }
        }

        if (!$this->isUpdate) {
            // 新規作成の場合はソート番号を設定（ブログ・カテゴリー・ユーザーの決定後に設定する必要がある）
            if (!is_int($this->entry['entry_sort'])) {
                $this->entry['entry_sort'] = $this->nextEntrySort($this->entry['entry_blog_id']);
            }
            if (!is_int($this->entry['entry_user_sort'])) {
                $this->entry['entry_user_sort'] = $this->nextEntryUserSort($this->entry['entry_user_id'], $this->entry['entry_blog_id']);
            }
            if (!is_int($this->entry['entry_category_sort'])) {
                $this->entry['entry_category_sort'] = $this->nextEntryCategorySort($this->entry['entry_category_id'], $this->entry['entry_blog_id']);
            }
        }

        // アップデートの場合は余分なベース情報を削除
        if ($this->isUpdate) {
            foreach ($this->entry as $key => $value) {
                if (!isset($this->data[$key])) {
                    unset($this->entry[$key]);
                }
            }
        }
    }

    /**
     * エントリーフィールドの組み立て
     *
     * @param string $key フィールドキー
     * @param string $value フィールド値
     * @return void
     */
    private function buildEntry(string $key, string $value): void
    {
        switch ($key) {
            case 'entry_id':
            case 'entry_blog_id':
                break;
            case 'entry_datetime':
            case 'entry_updated_datetime':
            case 'entry_start_datetime':
            case 'entry_end_datetime':
                if (preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                    $this->entry[$key] = $value;
                }
                break;
            case 'entry_code':
                if ($value !== '') {
                    // エントリーコードを空で上書きできるようにするために空文字でないときのみ加工処理を行う
                    $value = (string) preg_replace('@\.([^\.]+)$@', '', $value);
                    if ($value !== '') {
                        $value = Entry::formatEntryCode($value);
                    }
                }
                $this->entry[$key] = $value;
                break;
            case 'entry_posted_datetime':
                if (preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                    $this->entry[$key] = $value;
                    $this->entry['entry_hash'] = md5(SYSTEM_GENERATED_DATETIME . $value);
                }
                break;
            case 'entry_category_id':
                $cid = intval($value);
                $ccode = ACMS_RAM::categoryCode($cid);
                if ($ccode === null || $ccode === '') {
                    $this->entry[$key] = null;
                } else {
                    $this->entry[$key] = $cid;
                }
                break;
            default:
                $this->entry[$key] = $value;
        }
    }

    /**
     * 位置情報の組み立て
     *
     * @param string $key 位置情報キー（geo_lat, geo_lng, geo_zoom）
     * @param string $value 位置情報値
     * @return void
     */
    private function buildGeo(string $key, string $value): void
    {
        switch ($key) {
            case 'geo_lat':
                $this->geoLat = (float)$value;
                break;
            case 'geo_lng':
                $this->geoLng = (float)$value;
                break;
            case 'geo_zoom':
                $this->geoZoom = (int)$value;
                break;
        }
    }

    /**
     * サブカテゴリーの組み立て
     *
     * @param string $value カンマ区切りのカテゴリーID文字列
     * @return void
     */
    private function buildSubCategory(string $value): void
    {
        $subCategoryIds = explode(',', $value);
        if ($subCategoryIds === false) {
            return;
        }
        if (count($subCategoryIds) === 0) {
            return;
        }
        foreach ($subCategoryIds as $cid) {
            $cid = intval(trim($cid));
            if ($cid === 0) {
                continue;
            }
            $this->subCategories[] = $cid;
        }
    }

    /**
     * タグの組み立て
     *
     * @param string $value タグ文字列
     * @return void
     */
    private function buildTag(string $value): void
    {
        $tags = Common::getTagsFromString($value);
        if (count($tags) === 0) {
            return;
        }
        foreach ($tags as $sort => $tag) {
            $this->tags[] = $tag;
        }
    }

    /**
     * ユニットの組み立て
     *
     * @param array<string, mixed> $unit ユニットベースデータ
     * @param string $key ユニットキー（unit@で始まる）
     * @param string $value ユニット値
     * @return void
     */
    private function buildUnit(array $unit, string $key, string $value): void
    {
        $type   = substr($key, strlen('unit@'));
        $sort   = 1;
        if (preg_match('@\[\d+\]$@', $type, $matchs)) {
            $sort   = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $type    = preg_replace('@\[\d+\]$@', '', $type);
        }
        if ($sort < 1) {
            throw new \RuntimeException(
                'ユニットの添字は1以上を指定してください（' . $key . '）。入力された値: ' . $sort
            );
        }
        if ($type === null || $type === '') {
            return;
        }
        if ($type === 'block-editor') {
            $unit['column_type'] = 'block-editor';
            $unit['column_align'] = '';
            $unit['column_field_2'] = '';
        } else {
            $tokens = preg_split('@(#|\.)@', $type, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($tokens === false) {
                return;
            }
            $tag = array_shift($tokens);
            $unit['column_field_2'] = $tag;
            $id = '';
            $class  = '';
            while ($mark = array_shift($tokens)) {
                $val = array_shift($tokens);
                if (is_null($val)) {
                    continue;
                }
                if ('#' === $mark) {
                    $id = $val;
                } else {
                    $class = $val;
                }
            }

            $attr   = '';
            if ($id !== '') {
                $attr .= ' id="' . $id . '"';
            }
            if ($class !== '') {
                $attr .= ' class="' . $class . '"';
            }
            if ($attr !== '') {
                $unit['column_attr'] = $attr;
            }
        }
        $unit['column_sort'] = $sort;
        $unit['column_field_1'] = $value;

        $this->units[] = $unit;
    }

    /**
     * エントリーベースデータを取得
     *
     * @return array<string, mixed> エントリーベースデータ
     */
    private function entryBase(): array
    {
        $posted_datetime = $this->getPostedDatetime();

        return [
            'entry_id'              => $this->nextId,
            'entry_code'            => Entry::generateEntryCode($this->nextId),
            'entry_status'          => 'open',
            'entry_sort'            => null,
            'entry_user_sort'       => null,
            'entry_category_sort'   => null,
            'entry_title'           => 'CSV_IMPORT-' . $this->nextId,
            'entry_link'            => '',
            'entry_datetime'        => $posted_datetime,
            'entry_start_datetime'  => '1000-01-01 00:00:00',
            'entry_end_datetime'    => '9999-12-31 23:59:59',
            'entry_posted_datetime' => $posted_datetime,
            'entry_updated_datetime' => $posted_datetime,
            'entry_hash'            => md5(SYSTEM_GENERATED_DATETIME . $posted_datetime),
            'entry_summary_range'   => null,
            'entry_indexing'        => 'on',
            'entry_members_only'    => 'off',
            'entry_primary_image'   => null,
            'entry_category_id'     => $this->importCid,
            'entry_user_id'         => SUID,
            'entry_blog_id'         => $this->importBid,
        ];
    }

    /**
     * ユニットベースデータを取得
     *
     * @return array<string, mixed> ユニットベースデータ
     */
    private function unitBase(): array
    {
        return [
            'column_id'         => $this->generateNewIdTrait(),
            'column_sort'       => 0,
            'column_align'      => 'auto',
            'column_type'       => 'text',
            'column_attr'       => '',
            'column_size'       => '',
            'column_field_1'    => '',
            'column_field_2'    => 'p',
            'column_field_3'    => '',
            'column_field_4'    => '',
            'column_field_5'    => '',
            'column_entry_id'   => $this->nextId,
            'column_blog_id'    => $this->importBid,
        ];
    }

    /**
     * フィールドベースデータを取得
     *
     * @return array<string, mixed> フィールドベースデータ
     */
    private function fieldBase(): array
    {
        return [
            'field_key'     => null,
            'field_value'   => null,
            'field_sort'    => 1,
            'field_search'  => 'on',
            'field_eid'     => $this->nextId,
            'field_blog_id' => $this->importBid,
        ];
    }
}
