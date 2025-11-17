<?php

namespace Acms\Services\Facades;

/**
 * @method static int getSummaryRange() サマリーの表示で使うユニットの範囲を取得
 * @method static void setSummaryRange(?int $summaryRange) サマリーの表示で使うユニットの範囲を設定
 * @method static array getUploadedFiles() アップロードされたファイルを取得
 * @method static void addUploadedFiles(string $path) アップロードされたファイルを追加
 * @method static bool isNewVersion() 新規バージョン作成の判定を取得
 * @method static void setNewVersion(bool $flag) 新規バージョン作成の判定をセット
 * @method static bool validEntryCodeDouble(string $code, int $bid, ?int $cid = null, ?int $eid = null) エントリーコードの重複をチェック
 * @method static \Field_Validation validTag(\Field_Validation $Entry) タグの重複をチェック
 * @method static \Field_Validation validSubCategory(\Field_Validation $Entry) サブカテゴリーの重複をチェック
 * @method static void entryDelete(int $eid, bool $changeRevision = false) エントリーを削除
 * @method static void revisionDelete(int $eid) リビジョンを削除
 * @method static int|false changeRevision(int $rvid, int $eid, int $bid) リビジョンを変更
 * @method static void saveSubCategory(int $eid, ?int $masterCid, array $cids, ?int $bid = null, ?int $rvid = null) サブカテゴリーを保存
 * @method static string[] getSubCategoryFromString(string $string, string $delimiter = ',') サブカテゴリーを文字列から配列に変換
 * @method static void saveRelatedEntries(int $eid, array $entryAry = [], int $rvid = null, array $typeAry = [], array $loadedTypes = []) 関連エントリーを保存
 * @method static int|false saveEntryRevision(int $eid, int $rvid, array $entryAry, string $type = '', string $memo = '') エントリーのリビジョンを保存
 * @method static bool saveFieldRevision(int $eid, \Field $Field, int $rvid) カスタムフィールドのバージョンを保存
 * @method static bool updateCacheControl(string $start, string $end, ?int $bid = null, ?int $eid = null) キャッシュを更新
 * @method static bool deleteCacheControl(?int $eid = null) キャッシュを削除
 * @method static array getRevision(int $eid, int $rvid) リビジョンを取得
 * @method static bool canUseDirectEdit() 現在のログインユーザーがダイレクト編集を利用可能かどうかを判定する
 * @method static bool isDirectEditEnabled() 現在のログインユーザーのダイレクト編集機能が有効な状態かどうかを判定する
 * @method static bool canDelete(int $entryId) 現在のログインユーザーがエントリーを削除可能かどうかを判定する
 * @method static bool canBulkDelete(int $blogId, ?int $categoryId = null) 現在のログインユーザーがエントリーを一括削除可能かどうかを判定する
 * @method static bool canDeleteAllFromTrash(int $blogId, ?int $categoryId = null) 現在のログインユーザーがゴミ箱からエントリーを削除可能かどうかを判定する
 * @method static bool canTrashRestore(int $entryId) 現在のログインユーザーがゴミ箱からエントリーを復元可能かどうかを判定する
 * @method static bool canBulkTrashRestore(int $blogId, ?int $categoryId = null) 現在のログインユーザーがゴミ箱からエントリーを一括復元可能かどうかを判定する
 * @method static bool canChangeOrder(string $type, int $blogId) 現在のログインユーザーがエントリーの表示順を変更可能かどうかを判定する
 * @method static bool canChangeOrderByOtherUser(int $blogId) 現在のログインユーザーが自分以外のユーザーで絞り込んだエントリーの表示順を変更可能かどうかを判定する
 * @method static bool canBulkStatusChange(int $blogId, ?int $categoryId = null) 現在のログインユーザーがエントリーのステータスを一括変更可能かどうかを判定する
 * @method static bool canBulkUserChange(int $blogId, ?int $categoryId = null) 現在のログインユーザーがエントリーのユーザーを一括変更可能かどうかを判定する
 * @method static bool canBulkCategoryChange(int $blogId, ?int $categoryId = null) 現在のログインユーザーがエントリーのカテゴリーを一括変更可能かどうかを判定する
 * @method static bool canBulkBlogChange(int $blogId) 現在のログインユーザーがエントリーのブログを一括変更可能かどうかを判定する
 * @method static bool canViewApprovalHistory(int $entryId) 現在のログインユーザーがエントリーの承認履歴を閲覧可能かどうかを判定する
 * @method static bool canDuplicate(int $entryId) 現在のログインユーザーがエントリーを複製可能かどうかを判定する
 * @method static bool canBulkDuplicate(int $blogId) 現在のログインユーザーがエントリーを一括複製可能かどうかを判定する
 * @method static bool canExport(int $blogId) 現在のログインユーザーがエントリーをエクスポート可能かどうかを判定する
 * @method static bool setTempUnitData(\Acms\Services\Unit\UnitCollection|array $data) 一時的にユニットデータを変数に保存
 * @method static \Acms\Services\Unit\UnitCollection|array|null getTempUnitData() 一時的に保存したユニットデータを取得
 * @method static bool canUpdate(int $eid, int $bid, ?int $cid = null, ?int $rvid = null) 現在のログインユーザーがエントリーの更新権限を持っているかどうかを判定する
 * @method static bool canEditView(int $eid, int $bid, ?int $cid = null) 現在のログインユーザーがエントリーの編集画面の閲覧権限を持っているかどうかを判定する
 */
class Entry extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'entry';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
