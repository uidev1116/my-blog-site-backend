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
        $resource = $this->loadGdImageFromPath($srcPath);
        $srcWidth = imagesx($resource);
        $srcHeight = imagesy($resource);
        [$srcWidth, $srcHeight, $x, $y, $column, $rows] = $this->computeEditMetrics($srcWidth, $srcHeight, $width, $height, $size);

        // tranceparent
        $nrsrc = imagecreatetruecolor($column, $rows);
        if (0 <= ($idx = imagecolortransparent($resource))) {
            @imagetruecolortopalette($nrsrc, true, 256);
            $rgb = @imagecolorsforindex($resource, $idx);
            if ($idx = imagecolorallocate($nrsrc, $rgb['red'], $rgb['green'], $rgb['blue'])) {
                imagefill($nrsrc, 0, 0, $idx);
                imagecolortransparent($nrsrc, $idx);
            }
        } else {
            imagealphablending($nrsrc, false);
            if ($idx = imagecolorallocatealpha($nrsrc, 0, 0, 0, 127)) {
                imagefill($nrsrc, 0, 0, $idx);
            }
            imagesavealpha($nrsrc, true);
        }
        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($nrsrc); // true color に変換
        }
        imagecopyresampled($nrsrc, $resource, 0, 0, $x, $y, $column, $rows, $srcWidth, $srcHeight);

        if (function_exists('imagerotate') and ($angle = intval($angle))) {
            $nrsrc = imagerotate($nrsrc, $angle, 0);
        }
        // シャープネス
        // if (function_exists('imageconvolution')) {
        //     $filter = array(
        //         array( 0.0, -1.0, 0.0 ),
        //         array( -1.0, 5.5, -1.0 ),
        //         array( 0.0, -1.0, 0.0 )
        //     );
        //     $div = array_sum(array_map('array_sum', $filter));
        //     imageconvolution($nrsrc, $filter, $div, 0);
        // }

        $mimeType = $this->getMimeType($srcPath);
        $imageType = $this->detectImageExtenstion($mimeType);
        $this->outputImage($nrsrc, $imageType, $destPath);
    }

    /**
     * 画像を複製
     *
     * @param string $srcPath
     * @param string $destPath
     * @param string|null $format
     * @return void
     */
    public function copyImage(string $srcPath, string $destPath, string $format = null): void
    {
        if ($format) {
            $mimeType = $this->getMimeType($srcPath);
            $srcFormat = $this->detectImageExtenstion($mimeType, true);
            if ($srcFormat !== $format) {
                $resource = $this->loadGdImageFromPath($srcPath);
                // パレットベースの画像かどうかを確認し、TrueColorに変換
                if (!imageistruecolor($resource)) {
                    // パレットベースの画像をTrueColorに変換
                    imagepalettetotruecolor($resource);
                }
                // PNG画像の場合、TrueColorに変換後に透明度情報を保持するために必要
                // PNG画像でない場合は適用しても何も起こらない
                imagealphablending($resource, false);
                imagesavealpha($resource, true);

                $this->outputImage($resource, $format, $destPath);
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
     * @return void
     */
    public function resizeImage(string $srcPath, string $destPath, int $srcWidth, int $srcHeight, int $srcX, int $srcY, int $destWidth, int $destHeight, int $destX, int $destY, int $canvasWidth, int $canvasHeight, array $color): void
    {
        $resource = $this->loadGdImageFromPath($srcPath);
        if ($canvasWidth <= 0 || $canvasHeight <= 0) {
            throw new InvalidArgumentException('Canvas width and height must be greater than zero.');
        }
        $outputResource = imagecreatetruecolor($canvasWidth, $canvasHeight);

        if (0 <= ($idx = imagecolortransparent($resource))) {
            @imagetruecolortopalette($outputResource, true, 256);
            $rgb = @imagecolorsforindex($resource, $idx);
            if ($idx = imagecolorallocate($outputResource, $rgb['red'], $rgb['green'], $rgb['blue'])) {
                imagefill($outputResource, 0, 0, $idx);
                imagecolortransparent($outputResource, $idx);
            }
        } else {
            imagealphablending($outputResource, false);
            [$red, $green, $blue] = $color;
            if ($idx = imagecolorallocatealpha($outputResource, $red, $green, $blue, 127)) {
                imagefill($outputResource, 0, 0, $idx);
            }
            imagesavealpha($outputResource, true);
        }
        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($outputResource); // true color に変換
        }
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
        $mimeType = $this->getMimeType($srcPath);
        $imageType = $this->detectImageExtenstion($mimeType);
        $this->outputImage($outputResource, $imageType, $destPath);
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
