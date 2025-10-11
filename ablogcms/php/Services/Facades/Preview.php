<?php

namespace Acms\Services\Facades;

/**
 * @method static bool isPreviewMode() プレビューモード中か判定
 * @method static string|false getFakeUserAgent() 偽装ユーザーエージェントの取得
 * @method static bool isValidPreviewSharingUrl() プレビュー共有モードになれるか判定
 * @method static string getShareUrl(string $url, bool $lifetime = false) プレビュー共有URLの取得
 * @method static string getSharePreviewUrl() 共有URLで実際に表示するiFrameのURL
 * @method static void expiredShareUrl() 期限切れの共有URLを削除
 * @method static void startPreviewMode(string $fakeUserAgent, string $token) プレビューモードを開始
 * @method static void endPreviewMode() プレビューモードを終了
 * @method static bool isPreviewShareAdmin(string $admin) プレビュー共有URLの管理者か判定
 * @method static string getPreviewShareTpl() プレビュー共有URLのテンプレートを取得 *
 */
class Preview extends Facade
{
    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'preview';
    }
}
