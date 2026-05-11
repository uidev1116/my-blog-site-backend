<?php

namespace Acms\Services\Unit\Services\File;

use Acms\Services\Facades\LocalStorage;

/**
 * ファイルユニットの拡張子から fileicon ディレクトリ内の一致するアイコン名を解決する純粋ロジック。
 *
 * File.php::renderEdit() 内で行っていた preg_match 判定を、
 * 正規表現メタ文字を含む拡張子入力でもエラーを出さない形に切り出したもの。
 */
class FileIconResolver
{
    /**
     * @param string $ext      判定対象の拡張子 (ドットなし)
     * @param array<int, string> $iconFileList fileicon ディレクトリ配下のファイルパス一覧
     * @return string|null 一致した場合は入力拡張子をそのまま返す。一致しなければ null。
     */
    public static function resolve(string $ext, array $iconFileList): ?string
    {
        if ($ext === '') {
            return null;
        }
        $needle = strtolower($ext) . '.';
        foreach ($iconFileList as $filePath) {
            $basename = strtolower(LocalStorage::mbBasename($filePath));
            if ($basename === '') {
                continue;
            }
            if (strpos($basename, $needle) === 0) {
                return $ext;
            }
        }
        return null;
    }
}
