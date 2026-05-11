<?php

namespace Acms\Services\Template\Twig;

use Acms\Services\Facades\LocalStorage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * include デバッグコメント用のヘルパー。
 * Loader に登録されたパスを走査し、テンプレートが実在するディレクトリ付きのパスを返す。
 * CommentedIncludeNode（コンパイル済みコード）と CommentedIncludeExtension（関数）の両方から利用される。
 */
class IncludeCommentHelper
{
    /**
     * HTML コメントを挿入してよいテンプレートかどうかを判定する。
     * .twig を除去した上で拡張子を取得し、getMimetype() で text/html の場合のみ true を返す。
     * プロジェクトの mime.types および addtype 設定に準拠する。
     */
    public static function isHtmlTemplate(string $templateName): bool
    {
        $name = $templateName;
        if (str_ends_with($name, '.twig')) {
            $name = substr($name, 0, -5);
        }
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        return getMimetype($ext) === 'text/html';
    }

    /**
     * テンプレート名を、Loader の登録パス付きに解決する。
     *
     * FilesystemLoader の場合、登録ディレクトリ（例: themes/develop）を順に走査し、
     * テンプレートが見つかったディレクトリのパスを付与して返す。
     * 返すパスは Loader の登録パスから組み立てるため、シンボリックリンクやマウントの影響を受けない。
     */
    public static function resolveTemplatePath(string $templateName, Environment $env): string
    {
        $loader = $env->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return $templateName;
        }

        $name = ltrim($templateName, '/');

        try {
            foreach ($loader->getPaths() as $dir) {
                if (LocalStorage::isFile($dir . '/' . $name)) {
                    return $dir . '/' . $name;
                }
                if (LocalStorage::isFile($dir . '/' . $name . '.twig')) {
                    return $dir . '/' . $name . '.twig';
                }
            }
        } catch (\Exception $e) {
            // パスが未登録の場合など
        }

        return $templateName;
    }
}
