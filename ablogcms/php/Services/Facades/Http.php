<?php

namespace Acms\Services\Facades;

/**
 * @method static \Acms\Services\Http\Engine init(string $uri, string $method = "get") リクエストを初期化
 * @method static void setRequestHeaders(array $headers = []) リクエストヘッダーを設定
 * @method static void setPostData(array $data = []) リクエストデータを設定
 * @method static \Acms\Services\Http\Engine send() リクエストを送信
 * @method static string getResponseHeader(string|false $name = false) レスポンスヘッダーを取得
 * @method static string getResponseBody() レスポンスボディを取得
 * @method static void setCurlProxy(\CurlHandle $ch) cURLプロキシを設定
 */
class Http extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'http';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
