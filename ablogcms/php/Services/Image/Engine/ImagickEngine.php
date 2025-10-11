<?php

namespace Acms\Services\Image\Engine;

use Acms\Services\Image\Contracts\ImageEngine;
use Acms\Services\Facades\PublicStorage;
use Imagick;
use ImagickPixel;
use RuntimeException;

class ImagickEngine extends ImageEngine
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
        $imagick = $this->loadImagickFromPath($srcPath);
        $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
        $imagick->setImageCompressionQuality($this->getImageQuality());
        // $imagick->sharpenimage(0.8, 0.6); // シャープネス

        $imageprops = $imagick->getImageGeometry();
        $srcWidth = $imageprops['width'];
        $srcHeight = $imageprops['height'];
        [$srcWidth, $srcHeight, $x, $y, $column, $rows] = $this->computeEditMetrics($srcWidth, $srcHeight, $width, $height, $size);

        // tranceparent
        $imagick->cropImage($srcWidth, $srcHeight, $x, $y);
        $imagick->resizeImage($column, $rows, Imagick::FILTER_LANCZOS, 0.9, true);
        // rotate
        if ($angle = intval($angle)) {
            $imagick->rotateImage('none', -1 * $angle);
        }
        $imagick->stripImage();
        $this->outputImage($imagick, $destPath);
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
                $imagick = $this->loadImagickFromPath($srcPath);
                $imagick->implodeImage(0.0001);
                $imagick->setImageCompressionQuality($this->getImageQuality());
                $imagick->setFormat($format);
                $imagick->stripImage();
                $this->outputImage($imagick, $destPath);
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
        $this->optimize($destPath);
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
        $imagick = $this->loadImagickFromPath($srcPath);
        $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
        $imagick->setImageCompressionQuality($this->getImageQuality());
        $imagick->cropImage($srcWidth, $srcHeight, $srcX, $srcY);
        $imagick->resizeImage($destWidth, $destHeight, Imagick::FILTER_LANCZOS, 0.9, false);

        $mimeType = $this->getMimeType($srcPath);
        $format = $this->detectImageExtenstion($mimeType, true);

        if (in_array($format, ['gif', 'png'], true)) {
            $imagick->setImageBackgroundColor(new ImagickPixel('transparent'));
        } else {
            [$red, $green, $blue] = $color;
            $imagick->setImageBackgroundColor(new ImagickPixel("rgb($red, $green, $blue)"));
        }
        if ($destWidth === $canvasWidth) {
            // 横幅いっぱい
            $imagick->spliceImage(0, $destY, 0, 0);
            $imagick->spliceImage(0, $destY, 0, $destY + $destHeight);
        } else {
            // 縦幅いっぱい
            $imagick->spliceImage($destX, 0, 0, 0);
            $imagick->spliceImage($destX, 0, $destX + $destWidth, 0);
        }
        $imagick->stripImage();
        $this->outputImage($imagick, $destPath);
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
        if (!class_exists('Imagick') || config('webp_support') !== 'on') {
            return $supported = false;
        }
        $formats = array_map('strtolower', Imagick::queryFormats());
        $supported = in_array('webp', $formats, true);

        return $supported;
    }

    /**
     * 画像サイズを取得
     *
     * @param string $path
     * @return array{0: int, 1: int}
     */
    public function getSize(string $path): array
    {
        $imagick = $this->loadImagickFromPath($path);
        $imageprops = $imagick->getImageGeometry();
        $srcWidth = $imageprops['width'];
        $srcHeight = $imageprops['height'];
        $imagick->clear();
        $imagick->destroy();

        return [$srcWidth, $srcHeight];
    }

    /**
     * パスからImagickを生成
     *
     * @param string $path
     * @return Imagick
     */
    protected function loadImagickFromPath(string $path): Imagick
    {
        if ($this->isUploadedFile($path)) {
            return new Imagick($path);
        }
        $imageData = PublicStorage::get($path);
        if ($imageData === false) {
            throw new RuntimeException('Failed to load image data.');
        }
        $imagick = new Imagick();
        $imagick->readImageBlob($imageData);

        return $imagick;
    }

    /**
     * 書き出し
     *
     * @param Imagick $imagick
     * @param string $destPath
     * @return void
     */
    protected function outputImage(Imagick $imagick, string $destPath): void
    {
        if ($imageData = $imagick->getImageBlob()) {
            PublicStorage::put($destPath, $imageData);
            $this->optimize($destPath);
            PublicStorage::changeMod($destPath);
        }
        $imagick->clear();
        $imagick->destroy();
    }
}
