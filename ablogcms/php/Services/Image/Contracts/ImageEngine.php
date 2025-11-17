<?php

namespace Acms\Services\Image\Contracts;

use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\LocalStorage;

abstract class ImageEngine
{
    /**
     * Image Helper constructor.
     */
    public function __construct()
    {
    }

    /**
     * アップロードされたファイルかどうか判定
     *
     * @param string $path
     * @return boolean
     */
    public function isUploadedFile(string $path): bool
    {
        return is_uploaded_file($path) || strpos($path, 'php://') === 0;
    }

    /**
     * ユニークなファイルパスを生成
     *
     * @param string $path
     * @param string $prefix
     * @return string
     */
    public function uniqueFilePath(string $path, string $prefix): string
    {
        return PublicStorage::uniqueFilePath($path, $prefix);
    }

    /**
     * MIME Type テーブル
     *
     * @var array
     */
    private $mimeTypeMap = [
        'image/gif' => 'gif',
        'image/png' => 'png',
        'image/vnd.wap.wbmp' => 'bmp',
        'image/xbm' => 'xbm',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * 画質
     *
     * @var int
     */
    private $imageQuality = 75;

    /**
     * @var \Acms\Services\Image\ImagerOptimizer|null
     */
    private $optimizer;

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
    abstract public function editImage(string $srcPath, string $destPath, ?int $width = null, ?int $height = null, ?int $size = null, ?int $angle = null): void;

    /**
     * WebP画像がサポートされているか判定
     *
     * @return bool
     */
    abstract public function isWebpSupported(): bool;

    /**
     * 画像を複製
     *
     * @param string $srcPath
     * @param string $distPath
     * @param string|null $format
     * @return void
     */
    abstract public function copyImage(string $srcPath, string $distPath, ?string $format = null): void;

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
    abstract public function resizeImage(string $srcPath, string $destPath, int $srcWidth, int $srcHeight, int $srcX, int $srcY, int $destWidth, int $destHeight, int $destX, int $destY, int $canvasWidth, int $canvasHeight, array $color, ?int $angle = null): void;

    /**
     * WebP画像として画像を複製
     *
     * @param string $srcPath
     * @param string $distPath
     * @return void
     */
    abstract public function copyImageAsWebp(string $srcPath, string $distPath): void;

    /**
     * 画像サイズを取得
     *
     * @param string $path
     * @return array{0: int, 1: int}
     */
    abstract public function getSize(string $path): array;

    /**
     * 画質をセット
     *
     * @param int $quality
     * @return void
     */
    public function setImageQuality(int $quality): void
    {
        $this->imageQuality = $quality;
    }

    /**
     * 画質を取得
     *
     * @return int
     */
    public function getImageQuality(): int
    {
        return $this->imageQuality;
    }

    /**
     * 画像オプティマイザーを設定
     *
     * @param \Acms\Services\Image\ImagerOptimizer $optimizer
     * @return void
     */
    public function setOptimizer(\Acms\Services\Image\ImagerOptimizer $optimizer): void
    {
        $this->optimizer = $optimizer;
    }

    /**
     * ロスレス圧縮を実行
     *
     * @param string $path
     * @return void
     */
    public function optimize(string $path): void
    {
        if ($this->optimizer) {
            if ($this->isUploadedFile($path)) {
                $this->optimizer->optimize($path);
            } else {
                $imageData = PublicStorage::get($path);
                if (!$imageData) {
                    throw new \Exception("Failed to retrieve image: $path");
                }
                $tempFile = tempnam(sys_get_temp_dir(), 'imgopt_');
                LocalStorage::put($tempFile, $imageData);
                $this->optimizer->optimize($tempFile);

                if ($optimizedData = file_get_contents($tempFile)) {
                    PublicStorage::put($path, $optimizedData);
                }
                LocalStorage::remove($tempFile);
            }
        }
    }

    /**
     * 画像パスから画像拡張子を取得
     *
     * @param string $mimeType
     * @param bool $isOriginal
     * @return string
     */
    public function detectImageExtenstion(string $mimeType, $isOriginal = false): string
    {
        $extension = $this->mimeTypeMap[$mimeType] ?? null;
        if ($isOriginal) {
            return $extension;
        }
        if (in_array($extension, ['jpg', 'png'], true) && $this->isWebpSupported() && config('convert_2webp') === 'on') {
            return 'webp';
        }
        if ($extension === 'webp' && !$this->isWebpSupported()) {
            return 'jpg';
        }
        if (empty($extension)) {
            return 'jpg';
        }
        return $extension;
    }

    /**
     * 画像パスからMIMETypeを取得
     *
     * @param string $path
     * @return string|null
     */
    public function getMimeType(string $path): ?string
    {
        if ($this->isUploadedFile($path)) {
            return LocalStorage::getMimeType($path);
        }
        return PublicStorage::getMimeType($path);
    }

    /**
     * 画像パスから画像サイズを取得
     *
     * @param string $path
     * @return array{
     *  0: int,
     *  1: int,
     *  2: int,
     *  3: string,
     *  bits: int,
     *  channels: int,
     *  mime: string
     * }|false
     */
    public function getImageSize(string $path)
    {
        if ($this->isUploadedFile($path)) {
            return LocalStorage::getImageSize($path);
        }
        return PublicStorage::getImageSize($path);
    }

    /**
     * 画像編集をするための数値を計算
     *
     * @param int $srcWidth
     * @param int $srcHeight
     * @param int|null $width
     * @param int|null $height
     * @param int|null $size
     * @return array {
     *   width: int,
     *   height: int,
     *   x: int,
     *   y: int,
     *   columns: int,
     *   rows: int
     * }
     */
    public function computeEditMetrics(int $srcWidth, int $srcHeight, ?int $width = null, ?int $height = null, ?int $size = null): array
    {
        $longSide = max($srcWidth, $srcHeight);
        $ratio = 1;
        $coordinateX = 0;
        $coordinateY = 0;

        // square image
        if (!empty($width) and !empty($height) and !empty($size)) {
            if ($size < $longSide) {
                $columns = $size;
                $rows = $size;
                // landscape
                if ($srcWidth > $srcHeight) {
                    $coordinateX = ceil(($srcWidth - $srcHeight) / 2);
                    $srcWidth = $srcHeight;
                    // portrait
                } else {
                    $coordinateY = ceil(($srcHeight - $srcWidth) / 2);
                    $srcHeight = $srcWidth;
                }
            } else {
                // landscape
                if ($srcWidth > $srcHeight) {
                    $columns = $srcHeight;
                    $rows = $srcHeight;
                    $coordinateX = ceil(($srcWidth - $srcHeight) / 2);
                    $srcWidth = $srcHeight;
                    // protrait
                } else {
                    $columns = $srcWidth;
                    $rows = $srcWidth;
                    $coordinateY = ceil(($srcHeight - $srcWidth) / 2);
                    $srcHeight = $srcWidth;
                }
            }

            // normal, tiny, large
        } elseif (!empty($width) and $width < $srcWidth) {
            $ratio = $width / $srcWidth;
            $columns = $width;
            $rows = ceil($srcHeight * $ratio);
        } elseif (!empty($height) and $height < $srcHeight) {
            $ratio = $height / $srcHeight;
            $columns = ceil($srcWidth * $ratio);
            $rows = $height;
        } elseif (!empty($size) and $size < $longSide) {
            $ratio = $size / $longSide;
            $columns = ceil($srcWidth * $ratio);
            $rows = ceil($srcHeight * $ratio);
        } else {
            $columns = $srcWidth;
            $rows = $srcHeight;
        }
        return [$srcWidth, $srcHeight, $coordinateX, $coordinateY, $columns, $rows];
    }
}
