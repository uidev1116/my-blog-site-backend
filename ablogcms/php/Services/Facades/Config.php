<?php

namespace Acms\Services\Facades;

/**
 * @method static \Field load(?int $bid = null, ?int $rid = null, ?int $mid = null, ?int $setid = null) コンフィグを取得する
 * @method static true saveConfig(\Field $Config, ?int $bid = null, ?int $rid = null, ?int $mid = null, ?int $setid = null) コンフィグを保存する
 * @method static void resetConfig(\Field $Config, int $bid = null, ?int $rid = null, ?int $mid = null, ?int $setid = null) コンフィグをリセットする
 * @method static void forgetCache(?int $bid = null, ?int $rid = null, ?int $mid = null, ?int $setid = null) コンフィグキャッシュを削除する
 * @method static void forgetConfigSetNameCache(mixed $setid) コンフィグセット名キャッシュを削除する
 * @method static void cacheClear() コンフィグキャッシュを全て削除する
 * @method static array loadDefault() config.sytem.yamlに記録されているデフォルトのコンフィグを連想配列で返す
 * @method static \Field loadDefaultField() config.system.yamlに記録されているデフォルトのコンフィグをFieldで返す
 * @method static \Field loadBlogField(int $bid) ブログのコンフィグをFieldで返す
 * @method static \Field loadConfigSetField(int $id) コンフィグセットのコンフィグをFieldで返す
 * @method static \Field loadBlogConfig(int $bid) ブログのコンフィグをFieldで返す
 * @method static ?int getAncestorBlogConfigSet(int $bid, string $type) 先祖ブログのグローバル設定のコンフィグセットを取得する
 * @method static ?int getAncestorCategoryConfigSet(int $cid, string $type) 先祖カテゴリーのグローバル設定のコンフィグセットを取得する
 * @method static \Field loadBlogConfigSet(int $bid) 指定されたidに該当するブログのコンフィグセットを考慮したFieldを返す
 * @method static \Field loadBlogThemeSet(int $bid) 指定されたidに該当するブログのテーマセットを考慮したFieldを返す
 * @method static \Field loadBlogEditorSet(int $bid) 指定されたidに該当するブログの編集画面セットを考慮したFieldを返す
 * @method static \Field loadCategoryConfigSet(int $cid) 指定されたidに該当するカテゴリーのコンフィグセットを考慮したFieldを返す
 * @method static \Field loadCategoryThemeSet(int $cid) 指定されたidに該当するカテゴリーのテーマセットを考慮したFieldを返す
 * @method static \Field loadCategoryEditorSet(int $cid) 指定されたidに該当するカテゴリーの編集画面セットを考慮したFieldを返す
 * @method static ?int getCurrentConfigSetId() 現在のコンフィグセットのidを取得する
 * @method static ?int getCurrentThemeSetId() 現在のテーマセットのidを取得する
 * @method static ?int getCurrentEditorSetId() 現在の編集画面セットのidを取得する
 * @method static ?string getCurrentConfigSetName() 現在のコンフィグセットの名前を取得する
 * @method static ?string getCurrentThemeSetName() 現在のテーマセットの名前を取得する
 * @method static ?string getCurrentEditorSetName() 現在の編集画面セットの名前を取得する
 * @method static \Field loadRuleConfigSet(int $rid) 指定されたidに該当するルールのコンフィグセットを考慮したFieldを返す
 * @method static \Field loadRuleEditorSet(int $rid) 指定されたidに該当するルールの編集画面セットを考慮したFieldを返す
 * @method static \Field loadRuleThemeSet(int $rid) 指定されたidに該当するルールのテーマセットを考慮したFieldを返す
 * @method static \Field loadConfigSet(int $id) 指定されたidに該当するコンフィグセットのコンフィグをFieldで返す
 * @method static \Field loadRuleConfig(int $rid, ?int $setid = null) 指定されたidに該当するルールのコンフィグをFieldで返す
 * @method static \Field loadModuleConfig(int $mid, ?int $rid = null) 指定されたidに該当するモジュールIDのコンフィグをFieldで返す
 * @method static bool isExistsRuleModuleConfig() ルールモジュールのコンフィグが存在するか確認する
 * @method static mixed get(string $key, mixed $default = null, int $i = 0) 指定されたキーに該当するコンフィグを取得する
 * @method static array getArray(string $key, bool $strict = false) 指定されたキーに該当するコンフィグを配列で取得する
 * @method static true set(string $key, mixed $val = null) 現在のコンテキストにおける，指定されたキーのコンフィグを一時的に書き換える
 * @method static \Field_Validation setValide(\Field_Validation $Config, int $rid = null, int $mid = null, int $setid = null) コンフィグへのアクセス権限チェック
 * @method static bool canViewIndex(int $blogId) コンフィグ一覧を表示する権限があるかどうか
 * @method static bool isOperable(?int $rid = null, ?int $mid = null, ?int $setid = null) コンフィグの操作権限があるかどうか
 * @method static array getDataBaseSchemaInfo(string $type) タイプ指定によるデータベーススキーマの取得
 * @method static mixed yamlLoad(string $path) yamlファイルの取得
 * @method static mixed yamlParse(string $yaml) yamlファイルのパース
 * @method static mixed yamlDump(mixed $data, string $path = '') データをyamlに変換してファイルに書き出し
 * @method static \Field fix(\Field $Config) コンフィグ保存の為のデータ修正
 */
class Config extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'config';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
