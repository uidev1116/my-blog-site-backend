<?php

namespace Acms\Services\Storage;

use Acms\Services\Storage\Filesystem as BaseFileSystem;
use Acms\Services\Facades\Cache;
use League\Flysystem\Filesystem;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use AsyncAws\S3\S3Client;
use RuntimeException;
use Exception;

class S3 extends BaseFileSystem
{
    /**
     * @var \League\Flysystem\Filesystem
     */
    private $filesystem;

    /**
     * S3 constructor.
     *
     * @param S3Client $s3Client
     * @param string $bucketName
     * @param string $pathPrefix
     */
    public function __construct(S3Client $s3Client, string $bucketName, string $pathPrefix = '')
    {
        parent::__construct();

        $adapter = new AsyncAwsS3Adapter($s3Client, $bucketName, $pathPrefix);
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * ファイルの存在確認
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * 指定したパスがファイルかどうかを判定
     *
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool
    {
        return $this->exists($path);
    }

    /**
     * 指定したパスがディレクトリかどうかを判定
     *
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool
    {
        try {
            $contents = $this->filesystem->listContents($path, false);
            if (count(iterator_to_array($contents)) > 0) {
                return true;
            }
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * 実行可能ファイルかどうか
     *
     * @param string $path
     * @return bool
     */
    public function isExecutable(string $path): bool
    {
        throw new RuntimeException('Not support isExecutable method.');
    }

    /**
     * 書き込み可能かどうか
     *
     * @param string $path
     * @return bool
     */
    public function isWritable(string $path): bool
    {
        return true;
    }

    /**
     * 読み込み可能かどうか
     *
     * @param string $path
     * @return bool
     */
    public function isReadable(string $path): bool
    {
        return $this->exists($path);
    }

    /**
     * ファイルがシンボリックリンクかどうか
     *
     * @param string $path
     * @return bool
     */
    public function isLink(string $path): bool
    {
        return false;
    }

    /**
     * ファイルのパーミッションを変更する
     *
     * @param string $path
     * @param int|null $mode
     * @return bool
     */
    public function changeMod(string $path, ?int $mode = null): bool
    {
        return true;
    }

    /**
     * カレントディレクトリを変更する
     *
     * @param string $path
     * @return bool
     */
    public function changeDir(string $path): bool
    {
        throw new RuntimeException('Not support changeDir method.');
    }

    /**
     * ファイルサイズを取得する
     *
     * @param string $path
     * @return int
     */
    public function getFileSize(string $path): int
    {
        try {
            return $this->filesystem->fileSize($path);
        } catch (Exception $e) {
        }
        return 0;
    }

    /**
     * 画像サイズを取得する
     *
     * @param string $path
     * @param array $info
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
    public function getImageSize(string $path, array &$info = [])
    {
        static $cache = [];
        try {
            $cacheKey = md5($path);
            $cacheItem = $cache[$cacheKey] ?? null;
            if ($cacheItem !== null) {
                return $cacheItem;
            }
            if ($imageData = $this->get($path)) {
                $imageSize = getimagesizefromstring($imageData);
                // bitsとchannelsが存在しない場合はnullを設定
                $imageSize['bits'] = $imageSize['bits'] ?? 0;
                $imageSize['channels'] = $imageSize['channels'] ?? 0;
                $cache[$cacheKey] = $imageSize;
                return $imageSize;
            }
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ファイルのMIMEタイプを取得
     *
     * @param string $path
     * @return string|null
     */
    public function getMimeType(string $path): ?string
    {
        try {
            return $this->filesystem->mimeType($path);
        } catch (Exception $e) {
        }
        return null;
    }

    /**
     * ディレクトリ・トラバーサル対応のため、パスが公開領域のものか確認する
     *
     * @param string $path
     * @param string $publicDir
     * @return bool
     */
    public function validatePublicPath(string $path, string $publicDir = ''): bool
    {
        return true;
    }

    /**
     * ファイルを取得する
     *
     * @param string $path 取得したいファイルパス
     * @param string $publicDir 設定されたディレクトリ以下に取得できるファイルを制限（index.phpからの相対パス可）
     * @return string|false
     * @throws RuntimeException
     */
    public function get(string $path, string $publicDir = '')
    {
        try {
            return $this->filesystem->read($path);
        } catch (Exception $e) {
            throw new \RuntimeException("File does not exist at path {$path}");
        }
    }

    /**
     * ファイルをストリームで読み込む
     *
     * @param string $path
     * @return resource|false
     */
    public function readStream(string $path)
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ファイルを削除する
     *
     * @param string $path
     * @return bool
     */
    public function remove(string $path): bool
    {
        try {
            $this->filesystem->delete($path);
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ファイルを保存する
     *
     * @param string $path
     * @param string $content
     * @return int
     */
    public function put(string $path, string $content): int
    {
        try {
            $this->filesystem->write($path, $content);
            return strlen($content);
        } catch (Exception $e) {
            throw new RuntimeException('failed to put contents in ' . $path);
        }
    }

    /**
     * ファイルをコピーする
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy(string $from, string $to): bool
    {
        try {
            $this->filesystem->copy($from, $to);
            if ($this->exists("{$from}.webp")) {
                $this->filesystem->copy("{$from}.webp", "{$to}.webp");
            }
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ファイルを移動する
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move(string $from, string $to): bool
    {
        try {
            $this->filesystem->move($from, $to);
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ディレクトリを削除する
     *
     * @param string $dir
     * @return bool
     */
    public function removeDirectory(string $dir): bool
    {
        try {
            $this->filesystem->deleteDirectory($dir);
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ディレクトリをコピーする
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copyDirectory(string $from, string $to): bool
    {
        try {
            $files = $this->filesystem->listContents($from, true);
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $sourcePath = $file->path();
                    $destinationPath = str_replace($from, $to, $sourcePath);
                    $this->filesystem->copy($sourcePath, $destinationPath);
                } elseif ($file->isDir()) {
                    $sourcePath = $file->path();
                    $this->copyDirectory($sourcePath, str_replace($from, $to, $file->path()));
                }
            }
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ディレクトリを作成する
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        try {
            $this->filesystem->createDirectory(rtrim($path, '/'));
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * ファイルの更新日時を取得する
     *
     * @param string $path
     * @return int Unix time stamp
     */
    public function lastModified(string $path): int
    {
        try {
            return $this->filesystem->lastModified($path);
        } catch (Exception $e) {
        }
        return 0;
    }

    /**
     * ディレクトリ内のファイル一覧を取得する
     *
     * @param string $path
     * @return string[]
     */
    public function getFileList(string $path): array
    {
        try {
            $contents = $this->filesystem->listContents($path, false);
            $list = [];
            foreach ($contents as $item) {
                if ($item['type'] !== 'file') {
                    continue;
                }
                $list[] = $item['path'];
            }
            return $list;
        } catch (Exception $e) {
        }
        return [];
    }

    /**
     * 圧縮する
     *
     * @param string $source
     * @param string $destination
     * @param string $root
     * @param array $exclude
     * @return void
     */
    public function compress(string $source, string $destination, string $root = '', array $exclude = []): void
    {
        throw new RuntimeException('Not support compress method.');
    }

    /**
     * 解凍する
     *
     * @param string $source
     * @param string $destination
     * @return void
     */
    public function unzip(string $source, string $destination): void
    {
        throw new RuntimeException('Not support unzip method.');
    }
}
