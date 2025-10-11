<?php

namespace Acms\Services\Facades;

/**
 * @method static void optimize(string $path) 画像をロスレス圧縮
 * @method static bool optimizeTest(string $path) 画像のロスレス圧縮テスト
 * @method static bool copyImage(string $from, string $to, ?int $width = null, ?int $height = null, ?int $size = null, ?int $angle = null) 画像をコピー
 * @method static void resizeImg(string $srcPath, string $destPath, string $ext, ?int $width = null, ?int $height = null, ?int $size = null, ?int $angle = null) 画像をリサイズ
 * @method static bool isAvailableWebpWithGd() GDでWebPを生成可能かどうかを判定
 * @method static bool isAvailableWebpWithImagick() ImagickでWebPを生成可能かどうかを判定
 * @method static void createWebpWithGd(resource $resource, string $distPath, int $imageQuality = 75) GDでWebPを生成
 * @method static void createWebpWithImagick(string $srcPath, string $distPath, int $imageQuality = 75) ImagickでWebPを生成
 * @method static resource editImage(resource $rsrc, ?int $width = null, ?int $height = null, ?int $size = null, ?int $angle = null) 画像を編集（GD使用）
 * @method static void editImageForImagick(string $rsrc, string $file, ?int $width = null, ?int $height = null, ?int $size = null, ?int $angle = null) 画像を編集（Imagick使用）
 * @method static void deleteImageAllSize(string $path) 全サイズの画像削除
 * @method static string detectImageExtenstion(string $target_mime) 画像の拡張子を検出
 * @method static array{path: string, type: string, name: string, size: string} createImages(array{name: string, type: string, tmp_name: string, error: int, size: int} $File, array{normal?: int, tiny?: int, large?: int, square?: int} $sizes, string $destDir, bool $isRandomFileName = true, ?int $angle = null, bool $forceLarge = false) 画サイズ違い（tiny, square, large, normal）の画像を生成
 * @method static array|null getImageDimensions(string $path) 画像の幅と高さを取得
 */
class Image extends Facade
{
    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'image';
    }
}
