<?php

namespace Acms\Services\Common;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Storage\Contracts\Filesystem;

class Download
{
    private const STREAM_CHUNK_SIZE = 8192;

    /**
     * ファイルをダウンロードまたはインライン配信する。
     *
     * $extension を指定した場合は Content-Disposition: inline で返し、
     * HTTP Range リクエストに対応した部分配信を行う。
     *
     * このメソッドには、ディレクトリトラバーサル攻撃を防ぐための検証処理は含まれていません。
     * そのため、利用する際は事前に Storage::validateDirectoryTraversalPath などで、対象パスを必ず検証してください。
     *
     * @param string $path
     * @param string $fileName
     * @param string|false $extension 指定時は inline 配信
     * @param bool $remove 配信後にファイルを削除するか
     * @param \Acms\Services\Storage\Contracts\Filesystem|null $storage
     * @return never
     */
    public function handle(string $path, string $fileName, $extension = false, bool $remove = false, ?Filesystem $storage = null)
    {
        $storage = $storage ?? LocalStorage::getInstance();
        [$fp, $fileSize] = $this->openStreamOrFail($storage, $path);
        $meta = stream_get_meta_data($fp);

        if ($extension !== false && $meta['seekable']) {
            $this->sendInlineFile($fp, $fileName, $extension, $fileSize);
        } else {
            $this->sendAttachmentFile($fp, $fileName, $fileSize);
        }
        if ($remove) {
            $storage->remove($path);
        }
        exit;
    }

    /**
     * ストリームを開き、失敗時は 404 を送って終了する。
     *
     * @return array{0: resource, 1: int}
     */
    private function openStreamOrFail(Filesystem $storage, string $path): array
    {
        $fileSize = $storage->getFileSize($path);
        if ($fileSize === 0) {
            $this->sendNotFound();
        }
        $fp = $storage->readStream($path);
        if (!is_resource($fp)) {
            $this->sendNotFound();
        }
        return [$fp, $fileSize];
    }

    /**
     * 404 Not Found を送信して終了する。
     *
     * @return never
     */
    private function sendNotFound()
    {
        header('HTTP/1.1 404 Not Found');
        exit(1);
    }

    /**
     * インライン配信を行う。
     *
     * @param resource $fp
     * @param string $fileName
     * @param string $extension
     * @param int $fileSize
     */
    private function sendInlineFile($fp, string $fileName, string $extension, int $fileSize): void
    {
        if (!is_resource($fp)) {
            $this->sendNotFound();
        }

        $mime = $this->resolveInlineMime($extension);
        header('Content-Disposition: ' . $this->buildContentDisposition($fileName, 'inline'));
        header('Content-Type: ' . ($mime !== false && $mime !== '' ? $mime : 'application/octet-stream'));
        header('Accept-Ranges: bytes');

        $range = $this->parseSingleRange($_SERVER['HTTP_RANGE'] ?? '', $fileSize);
        if ($range === false) {
            fclose($fp);
            $this->respondRangeNotSatisfiable($fileSize);
        }

        [$start, $end] = $range ?? [0, $fileSize - 1];
        $contentLength = $end - $start + 1;
        $isPartial = is_array($range);

        if ($isPartial) {
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
        } else {
            header('HTTP/1.1 200 OK');
        }
        header('Content-Length: ' . $contentLength);

        $this->clearOutputBuffers();
        fseek($fp, $start);
        $this->streamFile($fp, $contentLength);
        fclose($fp);
    }

    /**
     * 添付ファイルとして配信する。
     *
     * @param resource $fp
     * @param string $fileName
     * @param int $fileSize
     */
    private function sendAttachmentFile($fp, string $fileName, int $fileSize): void
    {
        header('Content-Disposition: ' . $this->buildContentDisposition($fileName, 'attachment'));
        header('Content-Type: ' . $this->getAttachmentContentType());
        header('Content-Length: ' . $fileSize);

        $this->clearOutputBuffers();
        fpassthru($fp);
        fclose($fp);
    }

    /**
     * 添付配信用の Content-Type を返す（レガシー IE 用の分岐を含む）。
     */
    private function getAttachmentContentType(): string
    {
        $ua = defined('UA') ? UA : '';
        return strpos($ua, 'MSIE') !== false ? 'text/download' : 'application/octet-stream';
    }

    /**
     * Content-Disposition ヘッダの値を組み立てる。
     *
     * @param string $fileName
     * @param string $disposition inline または attachment
     * @return string
     */
    private function buildContentDisposition(string $fileName, string $disposition): string
    {
        $safeFileName = str_replace(["\r", "\n"], '', $fileName);
        $safeFileName = str_replace(['\\', '"'], ['\\\\', '\"'], $safeFileName);
        $encoded = rawurlencode($safeFileName);
        return "{$disposition}; filename=\"{$safeFileName}\"; filename*=UTF-8''{$encoded}";
    }

    /**
     * 出力バッファをすべて破棄する。
     */
    private function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * inline 配信用 MIME type を取得する。
     *
     * @param string $extension
     * @return string|false
     */
    private function resolveInlineMime(string $extension)
    {
        $inlineExtensions = configArray('media_inline_download_extension');

        foreach ($inlineExtensions as $i => $value) {
            if ($extension === $value) {
                return config('media_inline_download_mime', null, $i);
            }
        }

        return false;
    }

    /**
     * 単一の HTTP Range ヘッダーを解析する。
     *
     * 戻り値:
     * - null: Range 指定なし
     * - array{int,int}: [start, end]
     * - false: 不正な Range
     *
     * @param string $rangeHeader
     * @param int $fileSize
     * @return array<int,int>|null|false
     */
    private function parseSingleRange(string $rangeHeader, int $fileSize)
    {
        $rangeHeader = trim($rangeHeader);
        if ($rangeHeader === '') {
            return null;
        }

        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $matches)) {
            return false;
        }

        $startStr = $matches[1];
        $endStr = $matches[2];

        // bytes=-
        if ($startStr === '' && $endStr === '') {
            return false;
        }

        // bytes=-500（末尾から N バイト）
        if ($startStr === '') {
            $suffixLength = (int) $endStr;
            if ($suffixLength <= 0) {
                return false;
            }

            if ($suffixLength > $fileSize) {
                $suffixLength = $fileSize;
            }

            return [$fileSize - $suffixLength, $fileSize - 1];
        }

        // bytes=500-
        if ($endStr === '') {
            $start = (int) $startStr;
            if ($start >= $fileSize) {
                return false;
            }

            return [$start, $fileSize - 1];
        }

        // bytes=500-999
        $start = (int) $startStr;
        $end = (int) $endStr;

        if ($start > $end || $start >= $fileSize) {
            return false;
        }

        if ($end >= $fileSize) {
            $end = $fileSize - 1;
        }

        return [$start, $end];
    }

    /**
     * 416 Range Not Satisfiable を返す。
     *
     * @param int $fileSize
     * @return never
     */
    private function respondRangeNotSatisfiable(int $fileSize)
    {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */{$fileSize}");
        exit(1);
    }

    /**
     * ファイルポインタから指定バイト数を出力する。
     *
     * @param resource $fp
     * @param int $length
     */
    private function streamFile($fp, int $length): void
    {
        $remaining = $length;

        while ($remaining > 0 && !feof($fp)) {
            $readSize = min(self::STREAM_CHUNK_SIZE, $remaining);
            $buffer = fread($fp, $readSize);

            if ($buffer === false || $buffer === '') {
                break;
            }
            echo $buffer;
            flush();
            $remaining -= strlen($buffer);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }
}
