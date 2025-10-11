<?php

namespace Acms\Services\Image;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms_Hook;
use DOMDocument;

class Helper
{
    /**
     * @var \Acms\Services\Image\Contracts\ImageEngine
     */
    private $engine;

    /**
     * コンストラクター
     */
    public function __construct()
    {
        $this->engine =  Application::make('image.engine');
    }

    /**
     * 画像パスから画像タイプを取得
     *
     * @param string $mimeType
     * @return string
     */
    public function detectImageExtenstion(string $mimeType): string
    {
        return $this->engine->detectImageExtenstion($mimeType);
    }

    /**
     * 画像の複製
     *
     * @param string $from
     * @param string $to
     * @param int|null $width
     * @param int|null $height
     * @param int|null $size
     * @param int|null $angle
     *
     * @return bool
     */
    public function copyImage($from, $to, $width = null, $height = null, $size = null, $angle = null): bool
    {
        try {
            $xy = PublicStorage::getImageSize($from);
            if ($xy === false) {
                return false;
            }
            if (!PublicStorage::makeDirectory(dirname($to))) {
                return false;
            }
            [$srcWidth, $srcHeight] = $xy;
            $longSide = max($srcWidth, $srcHeight);

            if (
                ($width && $width < $srcWidth) ||
                ($height && $height < $srcHeight) ||
                ($size && $size < $longSide) ||
                $angle
            ) {
                $this->engine->editImage($from, $to, $width, $height, $size, $angle);
            } else {
                $this->engine->copyImage($from, $to);
            }
            $this->engine->copyImageAsWebp($to, "{$to}.webp");
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('mediaCreate', $to);
            }
            return true;
        } catch (\Exception $e) {
            Logger::error('画像の生成に失敗しました', Common::exceptionArray($e, ['path' => $to]));
        }
        return false;
    }

    /**
     * 画像のリサイズ
     *
     * @param string $srcPath
     * @param string $destPath
     * @param string $ext
     * @param int|null $width
     * @param int|null $height
     * @param int|null $size
     * @param int|null $angle
     * @return void
     */
    public function resizeImg($srcPath, $destPath, $ext, $width = null, $height = null, $size = null, $angle = null): void
    {
        try {
            $this->engine->editImage($srcPath, $destPath, $width, $height, $size, $angle);
            $this->engine->copyImageAsWebp($destPath, "{$destPath}.webp");
        } catch (\Exception $e) {
            Logger::error('画像の生成に失敗しました', Common::exceptionArray($e, ['path' => $destPath]));
        }
    }

    /**
     * 全サイズの画像削除
     *
     * @param string $path
     *
     * @return void
     */
    public function deleteImageAllSize($path)
    {
        if ($dirname = dirname($path)) {
            $dirname .= '/';
        }
        $basename   = PublicStorage::mbBasename($path);
        PublicStorage::remove("{$dirname}{$basename}");
        PublicStorage::remove("{$dirname}tiny-{$basename}");
        PublicStorage::remove("{$dirname}large-{$basename}");
        PublicStorage::remove("{$dirname}square-{$basename}");
        PublicStorage::remove("{$dirname}square64-{$basename}");

        PublicStorage::remove("{$dirname}{$basename}.webp");
        PublicStorage::remove("{$dirname}tiny-{$basename}.webp");
        PublicStorage::remove("{$dirname}large-{$basename}.webp");
        PublicStorage::remove("{$dirname}square-{$basename}.webp");
        PublicStorage::remove("{$dirname}square64-{$basename}.webp");

        $images = glob("{$dirname}*-{$basename}*");
        if (is_array($images)) {
            foreach ($images as $filename) {
                PublicStorage::remove($filename);
                if (HOOK_ENABLE) {
                    $Hook = ACMS_Hook::singleton();
                    $Hook->call('mediaDelete', $filename);
                }
            }
        }
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaDelete', "{$dirname}{$basename}");
            $Hook->call('mediaDelete', "{$dirname}tiny-{$basename}");
            $Hook->call('mediaDelete', "{$dirname}large-{$basename}");
            $Hook->call('mediaDelete', "{$dirname}square-{$basename}");

            $Hook->call('mediaDelete', "{$dirname}{$basename}.webp'");
            $Hook->call('mediaDelete', "{$dirname}tiny-{$basename}.webp");
            $Hook->call('mediaDelete', "{$dirname}large-{$basename}.webp");
            $Hook->call('mediaDelete', "{$dirname}square-{$basename}.webp");
        }
    }

    /**
     * サイズ違い（tiny, square, large, normal）の画像を生成
     *
     * @param array{
     *   name: string,
     *   type: string,
     *   tmp_name: string,
     *   error: int,
     *   size: int
     * } $File $_FILES[$name] で取得したファイル情報
     * @param array{
     *   normal?: int,
     *   tiny?: int,
     *   large?: int,
     *   square?: int
     * } $sizes
     * @param string $destDir
     * @param bool $isRandomFileName
     * @param int|null $angle
     * @param bool $forceLarge
     * @return array{
     *  path: string,
     *  type: string,
     *  name: string,
     *  size: string
     * }
     */
    public function createImages(
        array $File,
        array $sizes,
        string $destDir,
        bool $isRandomFileName = true,
        ?int $angle = null,
        bool $forceLarge = false
    ): array {
        $config = $this->createCreateImagesConfig(
            $File,
            $sizes,
            $destDir,
            $isRandomFileName,
            $angle,
            $forceLarge
        );

        return $this->createResizedImages($config);
    }

    /**
     * @param array{
     *   name: string,
     *   type: string,
     *   tmp_name: string,
     *   error: int,
     *   size: int
     * } $File $_FILES[$name] で取得したファイル情報
     * @param array{
     *   normal?: int,
     *   tiny?: int,
     *   large?: int,
     *   square?: int
     * } $sizes
     * @param string $destDir
     * @param bool $isRandomFileName
     * @param int|null $angle
     * @param bool $forceLarge
     * @return array{
     *   edit: array{
     *     tiny: array{
     *       size: int,
     *       angle?: int,
     *       side?: 'w' | 'h' | 'width' | 'height'
     *     },
     *     square?: array{
     *       size: int,
     *       angle?: int|null,
     *     },
     *     normal: array{
     *       size: int,
     *       angle?: int,
     *       side?: 'w' | 'h' | 'width' | 'height'
     *     },
     *     large?: array{
     *       size: int,
     *       angle?: int,
     *       side?: 'w' | 'h' | 'width' | 'height'
     *     }
     *   },
     *   srcPath: string,
     *   destPath: string,
     *   path: string,
     *   ext: 'gif' | 'png' | 'bmp' | 'xbm' | 'jpg',
     *   fileName: string
     * }
     */
    protected function createCreateImagesConfig(
        array $File,
        array $sizes,
        string $destDir,
        bool $isRandomFileName = true,
        ?int $angle = null,
        bool $forceLarge = false
    ): array {
        $tempPath = $File['tmp_name'];

        if ($tempPath === '') {
            throw new \InvalidArgumentException('Uploaded image file not found.');
        }
        if (is_uploaded_file($tempPath) === false) {
            throw new \InvalidArgumentException('Uploaded image file not found.');
        }

        $path = '';
        $ext = '';
        $edit = [];

        $normalSize = isset($sizes['normal']) ? strval($sizes['normal']) : '640';
        $tinySize = isset($sizes['tiny']) ? strval($sizes['tiny']) : '280';
        $largeSize = isset($sizes['large']) ? strval($sizes['large']) : '1200';
        $squareSize = isset($sizes['square']) ? intval($sizes['square']) : 300;

        ///* [CMS-762] (1).辺(string)と、px値(int)に分解する
        $stdSide = null;
        $stdSideTiny = null;
        $stdSideLarge = null;

        // normal
        if (preg_match('/^(w|width|h|height)(\d+)/', $normalSize, $matches)) {
            $stdSide = strval($matches[1]);
            $normalSize = intval($matches[2]);
        } else {
            $normalSize = intval($normalSize);
        }
        // tiny
        if (preg_match('/^(w|width|h|height)(\d+)/', $tinySize, $matches)) {
            $stdSideTiny = strval($matches[1]);
            $tinySize = intval($matches[2]);
        } else {
            $tinySize = intval($tinySize);
        }
        // large
        if (preg_match('/^(w|width|h|height)(\d+)/', $largeSize, $matches)) {
            $stdSideLarge = strval($matches[1]);
            $largeSize = intval($matches[2]);
        } else {
            $largeSize = intval($largeSize);
        }

        if ($squareSize < 1) {
            $squareSize = -1;
        }

        if ($normalSize !== 0 && $normalSize < $tinySize) {
            $tinySize = $normalSize;
        }

        $fileName = $File['name'];

        /**
         * @var array{
         *  0: int,
         *  1: int,
         *  2: int,
         *  3: string,
         *  bits: int,
         *  channels: int,
         *  mime: string
         * }|false $imageInfo
         * */
        $imageInfo = LocalStorage::getImageSize($tempPath);
        if (!$imageInfo) {
            throw new \RuntimeException('Failed to get image info.');
        }

        /** @var int $longSide */
        $longSide = max($imageInfo[0], $imageInfo[1]);
        $mime = $imageInfo['mime'];

        $edit['tiny'] = [
            'size'  => $tinySize,
            'angle' => $angle,
            'side'  => $stdSideTiny,
        ];

        if ($squareSize > 0) {
            $edit['square'] = [
                'size'  => $squareSize,
                'angle' => $angle,
            ];
        }

        $edit['normal'] = [
            'size'  => $normalSize,
            'angle' => $angle,
            'side'  => $stdSide,
        ];

        if ($forceLarge || (!empty($normalSize) && $longSide > $normalSize)) {
            $edit['large'] = [
                'size'  => ($longSide > $largeSize) ? $largeSize : $longSide,
                'angle' => $angle,
                'side'  => $stdSideLarge,
            ];
        }

        $archivesDir = PublicStorage::archivesDir();

        PublicStorage::makeDirectory($destDir . $archivesDir);
        $ext = $this->engine->detectImageExtenstion($mime);

        $fileNameParts = preg_split('/\./', $fileName);
        if ($fileNameParts === false) {
            throw new \RuntimeException('Failed to split file name.');
        }
        array_pop($fileNameParts);
        $fileName = implode('.', $fileNameParts);
        $fileName = preg_replace('/\s/u', '_', $fileName);
        if (preg_match('@^(large|tiny|square)@', $fileName)) {
            $fileName = "img_{$fileName}";
        }
        if (!$isRandomFileName) {
            $path = "{$archivesDir}{$fileName}.{$ext}";
            $path = $this->engine->uniqueFilePath($path, $destDir);
        } else {
            $path = $archivesDir . uniqueString(8) . '.' . $ext;
        }
        $destPath = "{$destDir}{$path}";

        return [
            'edit' => $edit,
            'srcPath' => $tempPath,
            'destPath' => $destPath,
            'path' => $path,
            'ext' => $ext,
            'fileName' => $fileName,
        ];
    }

    /**
     * @param array{
     *   edit: array{
     *     tiny: array{
     *       size: int,
     *       angle?: int,
     *       side?: 'w' | 'h' | 'width' | 'height'
     *     },
     *     square?: array{
     *       size: int,
     *       angle?: int|null,
     *     },
     *     normal: array{
     *       size: int,
     *       angle?: int,
     *       side?: 'w' | 'h' | 'width' | 'height'
     *     },
     *     large?: array{
     *       size: int,
     *       angle?: int,
     *       side?: 'w' | 'h' | 'width' | 'height'
     *     }
     *   },
     *   srcPath: string,
     *   destPath: string,
     *   path: string,
     *   ext: 'gif' | 'png' | 'bmp' | 'xbm' | 'jpg',
     *   fileName: string
     * } $config
     * @return array{
     *  path: string,
     *  type: string,
     *  name: string,
     *  size: string
     * }
     */
    protected function createResizedImages(array $config): array
    {
        if ($config['srcPath'] === '') {
            throw new \RuntimeException('Source file path not found.');
        }

        if ($config['destPath'] === '') {
            throw new \RuntimeException('Destination file path not found.');
        }

        $normalSize = '';
        $angleSrc = false;
        $isOriginalUpload = false;

        foreach (array_keys($config['edit']) as $sizeType) {
            /** @var 'tiny'| 'square' | 'normal' | 'large' $sizeType */

            /**
             * @var array{
             *     size: int,
             *     angle?: int,
             *     side?: 'w' | 'h' | 'width' | 'height'
             *  } $editConfig
             */
            $editConfig = $config['edit'][$sizeType];

            $pfx = ('normal' === $sizeType) ? '' : $sizeType . '-';
            /** @var string $destPath */
            $destPath = preg_replace('@(.*/)([^/]*)$@', '$1' . $pfx . '$2', $config['destPath']);
            if (!preg_match('@\.([^.]+)$@', $destPath, $match)) {
                continue;
            }
            $ext = $config['ext'];
            $size = $editConfig['size'] > 0 ? $editConfig['size'] : null;
            $angle = !is_null($editConfig['angle']) ? $editConfig['angle'] : null;

            $width = null;
            $height = null;

            // width
            if (
                in_array($sizeType, ['normal', 'tiny', 'large'], true) &&
                in_array($editConfig['side'], ['w', 'width'], true)
            ) {
                $width = $size;
                $size  = null;
            }
            // height
            if (
                in_array($sizeType, ['normal', 'tiny', 'large'], true) &&
                in_array($editConfig['side'], ['h', 'height'], true)
            ) {
                $height = $size;
                $size = null;
            }

            // square
            if ($sizeType === 'square') {
                $width = $size;
                $height = $size;
            }

            // 回転された画像をさらに回転処理しないように
            if ($angleSrc) {
                $angle = null;
            }
            if (!$angleSrc && $config['srcPath'] === $destPath) {
                $angleSrc = true;
            }

            if (
                (is_null($width) && is_null($height) && is_null($size)) // オリジナルのアップロード画像
                || $isOriginalUpload && $sizeType === 'large'
            ) {
                if (is_uploaded_file($config['srcPath'])) {
                    $this->engine->copyImage($config['srcPath'], $destPath, $ext);
                    $this->engine->copyImageAsWebp($destPath, "{$destPath}.webp");
                    $isOriginalUpload = true;
                }
            } else {
                $this->resizeImg($config['srcPath'], $destPath, $ext, $width, $height, $size, $angle);
            }
            if ($sizeType === 'normal') {
                /**
                 * @var array{
                 *  0: int,
                 *  1: int,
                 *  2: int,
                 *  3: string,
                 *  bits: int,
                 *  channels: int,
                 *  mime: string
                 * }|false $xy
                 * */
                $xy = $this->engine->getSize($destPath);
                $normalSize = $xy[0] . ' x ' . $xy[1];
            }
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('mediaCreate', $destPath);
            }
        }

        return [
            'path'  => $config['path'],
            'type'  => strtoupper($config['ext']),
            'name'  => $config['fileName'],
            'size'  => $normalSize,
        ];
    }

    /**
     * SVGの長さをパースして、ピクセル値を返す
     *
     * @param string $value
     * @return float|null
     */
    public function parseSvgLength(string $value): ?float
    {
        if (preg_match('/^([\d.]+)(px)?$/', trim($value), $matches)) {
            return (float) $matches[1];
        }

        // 単位がない場合や % の場合など → 無視（viewBox fallback）
        return null;
    }

    /**
     * 画像の幅と高さを取得
     *
     * @param string $path
     * @return array|null
     */
    public function getImageDimensions(string $path): ?array
    {
        if (!PublicStorage::exists($path) || !PublicStorage::isReadable($path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // SVG（XML）
        if ($ext === 'svg') {
            $doc = new DOMDocument();
            $success = $doc->load($path);
            if (!$success) {
                return null;
            }
            $svg = $doc->documentElement;
            if (!$svg || strtolower($svg->tagName) !== 'svg') {
                return null;
            }
            $width = $this->parseSvgLength($svg->getAttribute('width'));
            $height = $this->parseSvgLength($svg->getAttribute('height'));
            // viewBox fallback
            if ((!$width || !$height) && $svg->hasAttribute('viewBox')) {
                $viewBoxStr = $svg->getAttribute('viewBox');
                $viewBox = preg_split('/[\s,]+/', $viewBoxStr);
                if (is_array($viewBox) && count($viewBox) === 4) {
                    [, , $vbWidth, $vbHeight] = array_map('floatval', $viewBox);
                    $width = $width ? $width : $vbWidth;
                    $height = $height ? $height : $vbHeight;
                }
            }
            if ($width && $height) {
                return [
                    'width' => $width,
                    'height' => $height,
                    'aspect_ratio' => $width / $height,
                    'type' => 'svg',
                ];
            }
            return null;
        }

        // ラスター画像（JPEG / PNG / GIF）
        $info = PublicStorage::getImageSize($path);
        if ($info) {
            return [
                'width' => $info[0],
                'height' => $info[1],
                'aspect_ratio' => $info[0] / $info[1],
                'type' => image_type_to_mime_type($info[2]),
            ];
        }
        return null;
    }
}
