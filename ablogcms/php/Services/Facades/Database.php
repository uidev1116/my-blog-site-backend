<?php

namespace Acms\Services\Facades;

/**
 * @method static \Acms\Services\Database\Engine\Base connect(array $dsn) データベース接続
 * @method static \Acms\Services\Database\Engine\Base reconnect(array $dsn) データベース再接続
 * @method static string getVersion() データベースバージョン取得
 * @method static mixed query(array{sql: string, params: list<mixed>|array<string, mixed>} $query, string $mode = 'row') クエリ実行
 * @method static mixed fetch(array{sql: string, params: list<mixed>|array<string, mixed>} | null $sql = null, bool $reset = false) クエリ結果取得
 * @method static mixed next(\PDOStatement $response) クエリ結果取得
 * @method static array columnMeta(int $column) カラムメタデータ取得
 * @method static bool checkConnection(array $dsn) データベースサーバーへの接続チェック
 * @method static bool checkConnectDatabase(array $dsn) データベースへの接続チェック
 * @method static void hook(&$sql) クエリ書き換え用Hook
 * @method static string quote(string $string) クオート処理
 * @method static \Acms\Services\Database\Engine\Base singleton(?array $dsn = null) データベースエンジンインスタンス取得
 * @method static \SQL|mixed subQuery(\SQL $query, bool $subquery = false) バージョンによって、サブクエリを使用するか分離するかを判断
 * @method static \Acms\Services\Database\Engine\Base persistent(?array $dsn = null) 持続接続
 * @method static array errorInfo() エラー情報取得
 * @method static string errorCode() エラーコード取得
 * @method static array|int|void time(?string $sql = null, ?int $time = null) 処理時間保存
 * @method static bool isFetched(array{sql: string, params: list<mixed>|array<string, mixed>} | null $sql = null) クエリがフェッチ済みかどうか
 * @method static int affected_rows() 影響を受けた行数取得
 * @method static int columnCount() カラム数取得
 * @method static mixed connection() データベース接続オブジェクト取得
 * @method static string charset() 文字コード取得
 * @method static void optimizeTable() テーブル最適化
 * @method static void setThrowException(bool $throw) 例外をスローするか設定
 * @method static bool getThrowException() 例外をスローするかの設定を取得
 */
class Database extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'db';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
