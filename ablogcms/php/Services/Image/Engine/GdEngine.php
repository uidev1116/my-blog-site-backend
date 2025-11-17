<?php

namespace Acms\Services\Image\Engine;

use Acms\Services\Image\Contracts\ImageEngine;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use RuntimeException;
use InvalidArgumentException;
use GdImage;

class GdEngine extends ImageEngine
{
    /**
     * 画像を編集（リサイズ・回転）して書き出す
     *
     * @param string $srcPath
     * @param string $destPath
     * @param int|null $width
     * @param int|null $height
     * @param int|null $size
     * @param int|null $angle
     * @return void
     */
    public function editImage(string $srcPath, string $destPath, ?int $width = null, ?int $height = null, ?int $size = null, ?int $angle = null): void
    {
        // 元画像を読み込み
        $resource = $this->loadGdImageFromPath($srcPath);
        $srcWidth = imagesx($resource);
        $srcHeight = imagesy($resource);
        // 出力サイズなど計算
        [$srcWidth, $srcHeight, $x, $y, $column, $rows] = $this->computeEditMetrics($srcWidth, $srcHeight, $width, $height, $size);
        // リサイズ・回転して保存
        $this->resizeImage($srcPath, $destPath, $srcWidth, $srcHeight, $x, $y, $column, $rows, 0, 0, $column, $rows, [255, 255, 255], $angle);
    }

    /**
     * 画像を複製
     *
     * @param string $srcPath
     * @param string $destPath
     * @param string|null $format
     * @return void
     */
    public function copyImage(string $srcPath, string $destPath, ?string $format = null): void
    {
        if ($format) {
            $mimeType = $this->getMimeType($srcPath);
            $srcFormat = strtolower($this->detectImageExtenstion($mimeType, true));

            // jpeg/jpg 揺れ対策
            $normalize = fn($f) => $f === 'jpeg' ? 'jpg' : $f;

            if ($normalize($srcFormat) !== $normalize($format)) {
                $resource = $this->loadGdImageFromPath($srcPath);
                // パレット画像をTrueColor化
                if (function_exists('imagepalettetotruecolor')) {
                    imagepalettetotruecolor($resource);
                }
                // 透過設定（PNGで有効、他では無害）
                imagealphablending($resource, false);
                imagesavealpha($resource, true);

                $this->outputImage($resource, $format, $destPath);
                imagedestroy($resource);
                return;
            }
        }
        if ($this->isUploadedFile($srcPath)) {
            if ($content = file_get_contents($srcPath)) {
                PublicStorage::put($destPath, $content);
            }
        } else {
            PublicStorage::copy($srcPath, $destPath);
        }
    }

    /**
     * 画像をリサイズ
     *
     * @param string $srcPath
     * @param string $destPath
     * @param int $srcWidth
     * @param int $srcHeight
     * @param int $srcX
     * @param int $srcY
     * @param int $destWidth
     * @param int $destHeight
     * @param int $destX
     * @param int $destY
     * @param int $canvasWidth
     * @param int $canvasHeight
     * @param array{0: int, 1: int, 2: int} $color
     * @param int|null $angle
     * @return void
     */
    public function resizeImage(string $srcPath, string $destPath, int $srcWidth, int $srcHeight, int $srcX, int $srcY, int $destWidth, int $destHeight, int $destX, int $destY, int $canvasWidth, int $canvasHeight, array $color, ?int $angle = null): void
    {

        $resource = $this->loadGdImageFromPath($srcPath);
        if ($canvasWidth <= 0 || $canvasHeight <= 0) {
            throw new InvalidArgumentException('Canvas width and height must be greater than zero.');
        }
        // 出力画像リソースを生成
        $outputResource = imagecreatetruecolor($canvasWidth, $canvasHeight);
        // 形式判定
        $mimeType = $this->getMimeType($srcPath);
        $imageType = $this->detectImageExtenstion($mimeType);
        [$red, $green, $blue] = $color;
        assert($red >= 0 && $red <= 255);
        assert($green >= 0 && $green <= 255);
        assert($blue >= 0 && $blue <= 255);
        // 透過処理
        if ($imageType === 'gif' && ($idx = imagecolortransparent($resource)) >= 0) {
            // GIF専用の透過色処理（TrueColor化する前にやる）
            $total = imagecolorstotal($resource);
            if ($idx < $total) {
                $rgb = imagecolorsforindex($resource, $idx);
                if ($transparent = imagecolorallocate($outputResource, $rgb['red'], $rgb['green'], $rgb['blue'])) {
                    imagefill($outputResource, 0, 0, $transparent);
                    imagecolortransparent($outputResource, $transparent);
                }
            }
        } elseif ($imageType === 'png' || $imageType === 'webp') {
            // PNG/WebP はアルファ保持
            imagealphablending($outputResource, false);
            if ($transparent = imagecolorallocatealpha($outputResource, $red, $green, $blue, 127)) {
                imagefill($outputResource, 0, 0, $transparent);
                imagesavealpha($outputResource, true);
            }
        } else {
            if ($bg = imagecolorallocate($outputResource, $red, $green, $blue)) {
                imagefill($outputResource, 0, 0, $bg);
            }
        }
        // TrueColor化
        if (function_exists('imagepalettetotruecolor') && !imageistruecolor($resource)) {
            imagepalettetotruecolor($resource);
        }
        // リサイズ（imagescale が使えれば優先）
        if (function_exists('imagescale')) {
            // imagescale + Lanczosフィルタで高品質縮小（IMG_NEAREST_NEIGHBOUR | IMG_BILINEAR_FIXED | IMG_BICUBIC | IMG_BICUBIC_FIXED）
            if ($srcX > 0 || $srcY > 0) {
                assert($srcWidth > 0);
                assert($srcHeight > 0);
                $cropped = imagecreatetruecolor($srcWidth, $srcHeight);
                imagealphablending($cropped, false);
                imagesavealpha($cropped, true);
                if ($transparent = imagecolorallocatealpha($cropped, $red, $green, $blue, 127)) {
                    imagefill($cropped, 0, 0, $transparent);
                }
                imagecopy($cropped, $resource, 0, 0, $srcX, $srcY, $srcWidth, $srcHeight);

                $scaled = imagescale($cropped, $destWidth, $destHeight, IMG_BILINEAR_FIXED);
                imagedestroy($cropped);
            } else {
                $scaled = imagescale($resource, $destWidth, $destHeight, IMG_BILINEAR_FIXED);
            }
            if ($scaled) {
                imagecopy($outputResource, $scaled, $destX, $destY, 0, 0, $destWidth, $destHeight);
                imagedestroy($scaled);
            }
        } else {
            imagecopyresampled(
                $outputResource,
                $resource,
                $destX,
                $destY,
                $srcX,
                $srcY,
                $destWidth,
                $destHeight,
                $srcWidth,
                $srcHeight,
            );
        }
        // 回転（背景を透明で保持）
        if ($angle && function_exists('imagerotate')) {
            [$red, $green, $blue] = $color;
            imagealphablending($outputResource, false);
            if ($transparent = imagecolorallocatealpha($outputResource, $red, $green, $blue, 127)) {
                $outputResource = imagerotate($outputResource, $angle, $transparent);
            }
            imagesavealpha($outputResource, true);
        }
        // パレット化して減色する（8bit）
        // imagetruecolortopalette($outputResource, false, 256);
        // 画像保存
        $this->outputImage($outputResource, $imageType, $destPath);

        imagedestroy($resource);
        imagedestroy($outputResource);
    }

    /**
     * WebP画像として画像を複製
     *
     * @param string $srcPath
     * @param string $destPath
     * @return void
     */
    public function copyImageAsWebp(string $srcPath, string $destPath): void
    {
        if (!$this->isWebpSupported()) {
            return;
        }
        $mimeType = $this->getMimeType($srcPath);
        if (!in_array($this->detectImageExtenstion($mimeType), ['png', 'jpg'], true)) {
            return;
        }
        $this->copyImage($srcPath, $destPath, 'webp');
    }

    /**
     * GDでWebP画像がサポートされているか判定
     *
     * @return bool
     */
    public function isWebpSupported(): bool
    {
        static $supported = null;

        if (isset($supported)) {
            return $supported;
        }
        if (!function_exists('imagewebp') || config('webp_support') !== 'on') {
            return $supported = false;
        }
        $gdInfo = gd_info();
        $supported = isset($gdInfo['WebP Support']) && $gdInfo['WebP Support'];

        return $supported;
    }

    /**
     * 画像パスからGDImage or resourceを生成
     *
     * @param string $path
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return GdImage
     */
    protected function loadGdImageFromPath(string $path)
    {
        // パス指定の場合はリソースに変換
        if (empty($path)) {
            throw new InvalidArgumentException('File path is empty.');
        }
        if ($this->isUploadedFile($path)) {
            if (!LocalStorage::isReadable($path)) {
                throw new InvalidArgumentException('File path is not readable.');
            }
            $mimeType = LocalStorage::getMimeType($path);
        } else {
            if (!PublicStorage::isReadable($path)) {
                throw new InvalidArgumentException('File path is not readable.');
            }
            $mimeType = $this->getMimeType($path);
        }
        $type = $this->detectImageExtenstion($mimeType);
        if (!$type) {
            throw new InvalidArgumentException('Unsupported image type.');
        }
        if ($this->isUploadedFile($path)) {
            $imageData = file_get_contents($path);
        } else {
            $imageData = PublicStorage::get($path);
        }
        if (!is_string($imageData)) {
            throw new InvalidArgumentException('Failed to read image data.');
        }
        $resource = imagecreatefromstring($imageData);
        if ($resource === false) {
            throw new RuntimeException('Failed to create image resource.');
        }
        return $resource;
    }

    /**
     * 画像ファイルを書き出し
     *
     * @param GdImage|false $resource
     * @param string $imageType
     * @param string $destPath
     * @throws \RuntimeException
     * @return void
     */
    protected function outputImage($resource, string $imageType, string $destPath): void
    {
        if (!$imageType || !$resource) {
            throw new RuntimeException('');
        }
        ob_start();
        if ($imageType === 'webp' && $this->isWebpSupported()) {
            imagewebp($resource, null, $this->getImageQuality());
        } elseif ($imageType === 'png') {
            imagepng($resource, null);
        } elseif ($imageType === 'gif') {
            imagegif($resource, null);
        } elseif ($imageType === 'bmp') {
            imagewbmp($resource, null);
        } elseif ($imageType === 'xbm') {
            imagexbm($resource, null);
        } else {
            imagejpeg($resource, null, $this->getImageQuality());
        }
        if ($outputData = ob_get_clean()) {
            PublicStorage::put($destPath, $outputData);
            $this->optimize($destPath);
            PublicStorage::changeMod($destPath);
        }
        imagedestroy($resource);
    }

    /**
     * 画像サイズを取得
     *
     * @param string $path
     * @return array{0: int, 1: int}
     */
    public function getSize(string $path): array
    {
        $resource = $this->loadGdImageFromPath($path);
        $srcWidth = imagesx($resource) ?: 0;
        $srcHeight = imagesy($resource) ?: 0;

        return [$srcWidth, $srcHeight];
    }
}
