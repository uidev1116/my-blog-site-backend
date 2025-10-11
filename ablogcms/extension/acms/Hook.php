<?php

namespace Acms\Custom;

use Acms\Services\Facades\Application;

/**
 * ユーザー定義のHookを設定します。
 */
class Hook
{
    /**
     * 起動時
     */
    public function init()
    {
        if (class_exists('\Acms\Plugins\ApiPreview\ServiceProvider')) {
            new \Acms\Plugins\ApiPreview\ServiceProvider();
        }
    }

    /**
     * ログイン判定前
     * @return void
     */
    public function beforeAuthenticate()
    {
    }

    /**
     * ログイン判定後
     * @return void
     */
    public function afterAuthenticate()
    {
        if (Application::exists('api-preview.service.preview')) {
            $previewService = Application::make('api-preview.service.preview');
            $previewService->processPreview();
        }
    }

    /**
     * 権限チェック
     * @param int $suid
     * @param int $bid
     * @return void
     */
    public function restrictionAuthority($suid, $bid)
    {

    }

    /**
     * header指定
     *
     * @param bool $cache キャッシュ利用
     */
    public function header($cache)
    {
        // header('Vary: User-Agent');
        // header('Vary: Accept-Encoding');
        // header('Vary: Accept-Language');
        // header('Vary: Cookie');
    }

    /**
     * クエリ発行前
     *
     * @param string $sql
     */
    public function query(&$sql)
    {
    }

    /**
     * ルール判定のカスタム値
     *
     * @param string $value
     */
    public function customRuleValue(&$value)
    {
        // ここで設定した値を、ルール判定に使用できるようになります。
        // $value = '';
    }

    /**
     * キャッシュルールに特殊ルールを追加
     *
     * @param string $customRuleString
     */
    public function addCacheRule(&$customRuleString)
    {
        // $customRuleString = UA_GROUP; // デバイスによってルールを分ける場合
    }

    /**
     * GETモジュール処理前
     * 解決前テンプレートの中間処理など
     *
     * @param string &$tpl
     * @param \ACMS_GET $thisModule
     */
    public function beforeGetFire(&$tpl, $thisModule)
    {
    }

    /**
     * GETモジュール処理後
     * 解決済みテンプレートの中間処理など
     *
     * @param string &$res
     * @param \ACMS_GET $thisModule
     */
    public function afterGetFire(&$res, $thisModule)
    {
    }

    /**
     * POSTモジュール処理前
     * $thisModuleのプロパティを参照・操作するなど
     *
     * @param \ACMS_POST $thisModule
     */
    public function beforePostFire($thisModule)
    {
    }

    /**
     * POSTモジュール処理後
     * $thisModuleのプロパティを参照・操作するなど
     *
     * @param \ACMS_POST $thisModule
     */
    public function afterPostFire($thisModule)
    {
    }

    /**
     * ビルド前（GETモジュール解決前）
     *
     * @param $tpl &$tpl テンプレート文字列
     */
    public function beforeBuild(&$tpl)
    {
    }

    /**
     * ビルド後（GETモジュール解決後）
     * ※ 空白の除去・文字コードの変換・POSTモジュールに対するSIDの割り当てなどはこの後に行われます
     *
     * @param string &$res レスポンス文字列
     */
    public function afterBuild(&$res)
    {
        // if ( ADMIN == 'entry-edit' || substr(ADMIN, 0, 9) === 'entry-add' || !ADMIN ) {
        //     $inlineCss = \Storage::get(SCRIPT_DIR . 'themes/uidev/dist/assets/index.css');
        //     $css = '<style>' . $inlineCss . '</style>';
        //     $res = preg_replace('@(?=<\s*/\s*head[^\w]*>)@i', $css, $res);
        // }
    }

    /**
     * HTTPレスポンス直前に呼ばれます
     *
     * @param string &$res レスポンス文字列
     */
    public function beforeResponse(&$res)
    {
    }

    /**
     * エントリー作成、更新時 または エントリーインポート時（CSV, WordPress, Movable Type）
     *
     * @param int $eid エントリーID
     * @param int $revisionId リビジョンID
     */
    public function saveEntry($eid, $revisionId)
    {
    }

    /**
     * フォーム Submit時
     *
     * @param array $mail 自動返信メール
     * @param array $mailAdmin 管理者宛メール
     */
    public function formSubmit($mail, $mailAdmin)
    {
    }

    /**
     * 承認通知
     *
     * @param array $data 通知データ
     * @param bool falseを設定するとデフォルトのメールが飛ばないように設定
     */
    public function approvalNotification($data, &$send = true)
    {
    }

    /**
     * 処理の一番最後のシャットダウン時
     *
     *
     */
    public function beforeShutdown()
    {
    }

    /**
     * グローバル変数の拡張
     *
     * @param array $globalVars
     */
    public function extendsGlobalVars(&$globalVars)
    {
        // $globalVars->set('key', 'var');
    }

    /**
     * 引用ユニット拡張
     * @param string $url 引用URL
     * @param string &$html 整形後HTML
     */
    public function extendsQuoteUnit($url, &$html)
    {
    }

    /**
     * ビデオユニット拡張
     * @param string $url URL
     * @param string &$id Video ID
     */
    public function extendsVideoUnit($url, &$id)
    {
        // $parsed_url = parse_url($url);
        // if ( !empty($parsed_url['path']) ) {
        //     $id = preg_replace('@/@', '', $parsed_url['path']);
        // }
    }

    /**
     * キャッシュのリフレッシュ時
     *
     */
    public function cacheRefresh()
    {
    }

    /**
     * キャッシュのクリア時
     *
     */
    public function cacheClear()
    {
    }

    /**
     * メディアデータ作成
     * @param string $path 作成先パス
     *
     */
    public function mediaCreate($path)
    {
    }

    /**
     * メディアデータ削除
     * @param string $path 削除パス
     *
     */
    public function mediaDelete($path)
    {
    }
}
