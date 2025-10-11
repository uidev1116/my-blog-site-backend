<?php

namespace Acms\Services\Logger;

use Acms\Services\Facades\Logger;

class Deprecated
{
    /**
     * @var string[]
     */
    private static $repository = [];
    /**
     * 開発モードか判定
     */
    private static function isDevelopmentMode(): bool
    {
        if (isDebugMode()) {
            return true;
        }
        return false;
    }

    /**
     * 一度だけ非推奨ログを出力する
     *
     * @param string $feature
     * @param array{
     *   since?: string,
     *   version?: string,
     *   alternative?: string,
     *   link?: string,
     *   hint?: string,
     * } $options
     *
     * @return void
     */
    public static function once(string $feature, array $options = [], array $context = []): void
    {
        if (!self::isDevelopmentMode()) {
            return;
        }

        $since = $options['since'] ?? '';
        $version = $options['version'] ?? '';
        $alternative = $options['alternative'] ?? '';
        $link = $options['link'] ?? '';
        $hint = $options['hint'] ?? '';

        $sinceMessage = $since ? " version {$since} から" : '';
        $removeMessage = $version ? " version {$version} で削除予定です。" : '今後のアップデートで削除される可能性があります。';
        $alternativeMessage = $alternative ? "\n代わりに{$alternative}を使用してください。" : '';
        $linkMessage = $link ? "\n参照: {$link}" : '';
        $hintMessage = $hint ? "\n注: {$hint}" : '';

        $message = "{$feature}は{$sinceMessage}非推奨の機能です。{$removeMessage}{$alternativeMessage}{$linkMessage}{$hintMessage}";

        if (in_array($message, self::$repository, true)) {
            return;
        }

        Logger::notice($message, $context);

        self::$repository[] = $message;
    }
}
