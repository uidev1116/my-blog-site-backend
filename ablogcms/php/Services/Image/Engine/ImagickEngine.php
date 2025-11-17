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
        // 元画像を読み込み
        $imagick = $this->loadImagickFromPath($srcPath);
        $imageprops = $imagick->getImageGeometry();
        $srcWidth = $imageprops['width'];
        $srcHeight = $imageprops['height'];
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
            $srcFormat = $this->detectImageExtenstion($mimeType, true);
            if ($srcFormat !== $format) {
                $imagick = $this->loadImagickFromPath($srcPath);
                $imagick->setFormat($format); // フォーマットだけ変更（圧縮設定はしない）
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
     * @param int|null $angle
     * @return void
     */
    public function resizeImage(string $srcPath, string $destPath, int $srcWidth, int $srcHeight, int $srcX, int $srcY, int $destWidth, int $destHeight, int $destX, int $destY, int $canvasWidth, int $canvasHeight, array $color, ?int $angle = null): void
    {
        $imagick = $this->loadImagickFromPath($srcPath);
        // 画像形式
        $mimeType = $this->getMimeType($srcPath);
        $format = $this->detectImageExtenstion($mimeType, true);
        // 背景色
        /** @var array{0:int,1:int,2:int} $color */
        [$red, $green, $blue] = $color;
        $red = max(0, min(255, $red));
        $green = max(0, min(255, $green));
        $blue = max(0, min(255, $blue));
        // もしアニメGIFなら → 変換せずにそのままコピー
        if ($format === 'gif' && $imagick->getNumberImages() > 1) {
            PublicStorage::copy($srcPath, $destPath);
            return;
        }
        // リニア色空間に変換
        $imagick->transformImageColorspace(Imagick::COLORSPACE_RGB);
        // 色深度を保持
        $depth = $imagick->getImageDepth();
        // クロップ
        $imagick->cropImage($srcWidth, $srcHeight, $srcX, $srcY);
        // 縮小前に軽くシャープ（プリシェーピング）
        $imagick->unsharpMaskImage(0, 0.3, 0.6, 0.02);
        // 縮小（縮小率が高い場合は段階的に縮小）
        $factor = 2; // 縮小ステップ
        while (
            $imagick->getImageWidth() / $factor > $destWidth &&
            $imagick->getImageHeight() / $factor > $destHeight
        ) {
            $imagick->resizeImage(
                intval($imagick->getImageWidth() / $factor),
                intval($imagick->getImageHeight() / $factor),
                Imagick::FILTER_LANCZOS,
                1
            );
        }
        $imagick->resizeImage($destWidth, $destHeight, Imagick::FILTER_LANCZOS, 1);
        // 背景色設定（透過 or RGB指定）
        if (in_array($format, ['gif', 'png'], true)) {
            $imagick->setImageBackgroundColor(new ImagickPixel('transparent'));
        } else {
            $imagick->setImageBackgroundColor(new ImagickPixel("rgb($red, $green, $blue)"));
        }
        // キャンバス調整（上下 or 左右に余白）
        if ($destWidth === $canvasWidth) {
            // 横幅いっぱい → 上下余白
            $imagick->spliceImage(0, $destY, 0, 0);
            $imagick->spliceImage(0, $destY, 0, $destY + $destHeight);
        } else {
            // 縦幅いっぱい → 左右余白
            $imagick->spliceImage($destX, 0, 0, 0);
            $imagick->spliceImage($destX, 0, $destX + $destWidth, 0);
        }
        // 回転
        if ($angle) {
            $bg = in_array($format, ['png','gif'], true) ? 'none' : "rgb($red, $green, $blue)";
            $imagick->rotateImage(new ImagickPixel($bg), -1 * $angle);
        }
        // メタデータ削除
        $imagick->stripImage();
        $imagick->profileImage('*', null);
        // 出力時に元の depth に合わせる
        $imagick->setImageDepth($depth);
        // リニア → sRGB に戻す
        $imagick->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        // 圧縮
        if ($format === 'png') {
            $imagick->setImageFormat('png');
            $imagick->quantizeImage(256, Imagick::COLORSPACE_RGB, 0, false, true);
            $imagick->setImageType(Imagick::IMGTYPE_PALETTEMATTE);
            $imagick->setOption('png:compression-level', '9');
            $imagick->setOption('png:compression-filter', '5');
            $imagick->setOption('png:compression-strategy', '1');
            $imagick->setOption('png:exclude-chunk', 'all');
        } elseif ($format === 'webp' && $this->isWebpSupported()) {
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($this->getImageQuality());
        } elseif ($format === 'jpg') {
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality($this->getImageQuality());
            $imagick->setInterlaceScheme(Imagick::INTERLACE_JPEG);
        } elseif ($format === 'gif') {
            $imagick->setImageFormat('gif');
            $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
        }
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
