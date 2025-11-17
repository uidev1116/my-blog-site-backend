<?php

namespace Acms\Services\Common;

use Symfony\Component\Mime\MimeTypes;
use Acms\Services\Facades\LocalStorage;

class MimeTypeValidator
{
    private MimeTypes $mimeTypes;

    public function __construct()
    {
        $this->mimeTypes = new MimeTypes();
    }

    /**
     * 指定されたパスのMIME Typeを調べ、許可された拡張子かどうか検証する
     *
     * @param array $allowedExtensions 例: ['JPG','jpeg','PNG']
     * @return bool
     */
    public function validateAllowedByContent(string $path, array $allowedExtensions): bool
    {
        try {
            $this->assertAllowedByContent($path, $allowedExtensions);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * 指定された拡張子が許可された拡張子かどうか検証する
     *
     * @param string|string[] $extension 例: 'jpg'
     * @param array $allowedExtensions 例: ['JPG','jpeg','PNG']
     * @return bool
     */
    public function validateAllowedExtension(string|array $extension, array $allowedExtensions): bool
    {
        // 拡張子許可リストを正規化
        $allowed = array_values(array_unique(
            array_map([$this, 'canonicalizeExtension'], $allowedExtensions)
        ));
        // string なら配列に変換
        $extensions = is_array($extension) ? $extension : [$extension];
        // 拡張子を正規化
        $normalized = array_map([$this, 'canonicalizeExtension'], $extensions);

        return count(array_intersect($normalized, $allowed)) > 0;
    }

    /**
     * 指定されたパスのMIME Typeを調べ、許可された拡張子かどうか検証する
     *
     * @param array $allowedExtensions 例: ['JPG','jpeg','PNG']
     * @throws \RuntimeException
     */
    public function assertAllowedByContent(string $path, array $allowedExtensions): void
    {
        // ファイルの存在と読み取り権限を確認
        if (!LocalStorage::exists($path) || !LocalStorage::isReadable($path)) {
            throw new \RuntimeException("FILE_NOT_FOUND_OR_UNREADABLE: {$path}");
        }
        // パスからMIMEを取得
        $mime = $this->sniffMimeType($path);
        if ($mime === null) {
            throw new \RuntimeException('CANNOT_DETECT_MIME');
        }
        // Symfony Mime で拡張子リストを取得
        $exts = $this->mimeTypes->getExtensions($mime);
        if (!$exts) {
            throw new \RuntimeException("UNSUPPORTED_MIME: {$mime}");
        }
        // 正規化
        $allowed = array_map([$this, 'canonicalizeExtension'], $allowedExtensions);
        $normalizedExts = array_map([$this, 'canonicalizeExtension'], $exts);

        // 許可拡張子に含まれるか判定
        if (!array_intersect($normalizedExts, $allowed)) {
            throw new \RuntimeException("NOT_ALLOWED_EXTENSION: " . implode(',', $normalizedExts));
        }
    }

    /**
     * MIMEタイプから拡張子を取得する
     *
     * @param string $mimeType
     * @return array
     */
    public function getExtensionsFromMimeType(string $mimeType): array
    {
        return $this->mimeTypes->getExtensions($mimeType);
    }

    /**
     * MIME Type を取得する
     *
     * @param string $path
     * @return string|null
     */
    public function sniffMimeType(string $path): ?string
    {
        $mime = null;

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            $mime = $mime === false ? null : $mime;
        }
        // 不明/汎用なら画像ヘッダで再判定
        if (($mime === null || $mime === 'application/octet-stream') && function_exists('exif_imagetype')) {
            $type = exif_imagetype($path);
            $map  = [
                IMAGETYPE_JPEG => 'image/jpeg',
                IMAGETYPE_PNG => 'image/png',
                IMAGETYPE_GIF => 'image/gif',
                IMAGETYPE_WEBP => 'image/webp',
                IMAGETYPE_BMP => 'image/bmp',
                IMAGETYPE_TIFF_II => 'image/tiff',
                IMAGETYPE_TIFF_MM => 'image/tiff',
            ];
            if ($type && isset($map[$type])) {
                $mime = $map[$type];
            }
        }
        return $mime;
    }

    /**
     * 拡張子の正規化
     *
     * @param string $ext
     * @return string
     */
    public function canonicalizeExtension(string $ext): string
    {
        $e = strtolower($ext);
        return match ($e) {
            'jpeg' => 'jpg',
            'tif' => 'tiff',
            default => $e,
        };
    }
}
