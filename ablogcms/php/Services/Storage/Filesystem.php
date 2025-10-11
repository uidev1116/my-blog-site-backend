<?php

namespace Acms\Services\Storage;

use Acms\Services\Storage\Contracts\Filesystem as FilesystemInterface;
use Acms\Services\Storage\Contracts\Base;
use Acms\Services\Facades\Logger;
use Alchemy\Zippy\Adapter\ZipExtensionAdapter;
use Symfony\Component\Filesystem\Path;
use Acms\Services\Facades\Cache;
use DirectoryIterator;
use RuntimeException;

class Filesystem extends Base implements FilesystemInterface
{
    /**
     * realpath の安全なラッパー関数
     *
     * @param string $path
     * @return string|false
     */
    public function safeRealpath(string $path)
    {
        if (strpos($path, "\0") !== false) {
            Logger::notice('realpath に null バイトが含まれています', ['path' => $path]);
            return false;
        }
        try {
            return realpath($path);
        } catch (\ValueError $e) {
            Logger::notice('realpath に失敗', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ファイルの存在確認
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        $path = $this->convertStrToLocal($path);
        $resolved = $this->safeRealpath($path);
        if ($resolved === false) {
            return false;
        }
        return file_exists($resolved);
    }

    /**
     * 指定したパスがファイルかどうかを判定
     *
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool
    {
        $path = $this->convertStrToLocal($path);
        $resolved = $this->safeRealpath($path);
        if ($resolved === false) {
            return false;
        }
        return is_file($resolved);
    }

    /**
     * 指定したパスがディレクトリかどうかを判定
     *
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool
    {
        $path = $this->convertStrToLocal($path);
        $resolved = $this->safeRealpath($path);
        if ($resolved === false) {
            return false;
        }
        return is_dir($resolved);
    }

    /**
     * 実行可能ファイルかどうか
     *
     * @param string $path
     * @return bool
     */
    public function isExecutable(string $path): bool
    {
        $path = $this->convertStrToLocal($path);

        return is_executable($path);
    }

    /**
     * 書き込み可能かどうか
     *
     * @param string $path
     * @return bool
     */
    public function isWritable(string $path): bool
    {
        $path = $this->convertStrToLocal($path);

        return is_writable($path);
    }

    /**
     * 読み込み可能かどうか
     *
     * @param string $path
     * @return bool
     */
    public function isReadable(string $path): bool
    {
        $path = $this->convertStrToLocal($path);

        return is_readable($path);
    }

    /**
     * ファイルがシンボリックリンクかどうか
     *
     * @param string $path
     * @return bool
     */
    public function isLink(string $path): bool
    {
        $path = $this->convertStrToLocal($path);

        return is_link($path);
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
        $path = $this->convertStrToLocal($path);

        if (is_null($mode)) {
            if ($this->isDirectory($path)) {
                $mode = intval($this->directoryMod);
            } else {
                $mode = intval($this->fileMod);
            }
        }
        if ($this->exists($path)) {
            return chmod($path, $mode);
        }
        return false;
    }

    /**
     * カレントディレクトリを変更する
     *
     * @param string $path
     * @return bool
     */
    public function changeDir(string $path): bool
    {
        $path = $this->convertStrToLocal($path);
        if ($this->exists($path)) {
            return chdir($path);
        }
        return false;
    }

    /**
     * ファイルサイズを取得する
     *
     * @param string $path
     * @return int
     */
    public function getFileSize(string $path): int
    {
        $filesize =  filesize($path);
        if (empty($filesize)) {
            return 0;
        }
        return $filesize;
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
        $cacheKey = md5($path);
        $cacheItem = $cache[$cacheKey] ?? null;
        if ($cacheItem !== null) {
            return $cacheItem;
        }
        if ($this->exists($path) && $this->isFile($path)) {
            $imageSize = getimagesize($path);
            $cache[$cacheKey] = $imageSize;

            return $imageSize;
        } elseif (preg_match('/^https?:\/\//', $path)) {
            $headers = get_headers($path);
            if (isset($headers[0]) && strpos($headers[0], '200 OK') !== false) {
                $imageSize = getimagesize($path);
                $cache[$cacheKey] = $imageSize;

                return $imageSize;
            }
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
        if ($finfo = finfo_open(FILEINFO_MIME_TYPE)) {
            $mimeType = finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mimeType ? $mimeType : null;
        }
        return null;
    }

    /*
     * 指定されたファイル名がディレクトリトラバーサルを含まないか検証し、絶対パスを返します。
     *
     * @param string $baseDir
     * @param string $fileName
     * @return string
     */
    public function validateDirectoryTraversal(string $baseDir, string $fileName): string
    {
        $fileName = basename($fileName);
        $realBaseDir = $this->safeRealpath($baseDir);

        if ($realBaseDir === false) {
            Logger::notice('ディレクトリトラバーサルの検証に失敗しました。ベースディレクトリが存在しません。', [
                'baseDir' => $baseDir,
            ]);
            throw new \RuntimeException('ベースディレクトリが存在しません。');
        }

        $realPath = $this->safeRealpath($realBaseDir . DIRECTORY_SEPARATOR . $fileName);

        // realpathに失敗した、またはベースディレクトリ外ならエラー
        if ($realPath === false || strpos($realPath, $realBaseDir) !== 0) {
            Logger::notice('不正なパスです。ディレクトリトラバーサルの可能性があります。', [
                'baseDir' => $baseDir,
                'fileName' => $fileName,
                'realBaseDir' => $realBaseDir,
                'realPath' => $realPath,
            ]);
            throw new \RuntimeException('不正なパスです。');
        }

        return $realPath;
    }

    /**
     * ディレクトリ・トラバーサル対応のため、パスが公開領域のものか確認する
     *
     * @param string $path
     * @param string $publicDir
     * @param bool $checkExists
     * @return bool
     */
    public function validateDirectoryTraversalPath($path, $publicDir = '', $checkExists = true)
    {
        if (!is_string($path)) {
            return false;
        }
        if (!is_string($publicDir)) {
            return false;
        }
        if ($publicDir === '') {
            // cms設置ディレクトリ以下
            $publicDir1 = dirname(SCRIPT_FILE);
            $publicDir2 = dirname($this->safeRealpath(SCRIPT_FILE));
        } else {
            // 指定されたディレクトリ以下
            $publicDir1 = Path::makeAbsolute($publicDir, SCRIPT_DIR);
            $publicDir2 = $this->safeRealpath($publicDir);
        }

        $absolutePath = Path::makeAbsolute($path, SCRIPT_DIR);
        $fileName = basename($path);

        if ($absolutePath === false) {
            return false;
        }
        if (empty($publicDir1) || empty($publicDir2)) {
            return false;
        }
        $secretFileNames = array_merge(configArray('secret_file_name'), ['config.server.php', '.env', '.htaccess']);
        $secretFileNames = array_values(array_unique($secretFileNames));
        if (in_array($fileName, $secretFileNames, true)) {
            return false;
        }
        if ($checkExists) {
            if ($this->exists($absolutePath) === false) {
                return false;
            }
            if ($this->isFile($absolutePath) === false) {
                return false;
            }
        }
        if (strpos($absolutePath, $publicDir1) !== 0 && strpos($absolutePath, $publicDir2) !== 0) {
            return false;
        }
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
        $path = $this->convertStrToLocal($path);

        if ($this->isFile($path) && $this->validateDirectoryTraversalPath($path, $publicDir)) {
            return @file_get_contents($path);
        }
        throw new RuntimeException("File does not exist at path {$path}");
    }

    /**
     * ファイルをストリームで読み込む
     *
     * @param string $path
     * @return resource|false
     */
    public function readStream(string $path)
    {
        return @fopen($path, 'rb');
    }

    /**
     * ファイルを削除する
     *
     * @param string $path
     * @return bool
     */
    public function remove(string $path): bool
    {
        $path = $this->convertStrToLocal($path);

        if ($this->exists($path) && $this->isFile($path)) {
            return @unlink($path);
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
        $path = $this->convertStrToLocal($path);
        $byte = file_put_contents($path, $content);
        if (is_int($byte)) {
            @$this->changeMod($path);
            return $byte;
        }
        throw new \RuntimeException('failed to put contents in ' . $path);
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
        $to = $this->convertStrToLocal($to);
        $from = $this->convertStrToLocal($from);
        $res = @copy($from, $to);
        $this->changeMod($to);

        if ($this->isFile($from . '.webp')) {
            @copy($from . '.webp', $to . '.webp');
            $this->changeMod($to . '.webp');
        }
        return $res;
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
        $to = $this->convertStrToLocal($to);
        $from = $this->convertStrToLocal($from);

        $res = @rename($from, $to);
        $this->changeMod($to);

        return $res;
    }

    /**
     * ディレクトリを削除する
     *
     * @param string $dir
     * @return bool
     */
    public function removeDirectory(string $dir): bool
    {
        if (!$this->isDirectory($dir)) {
            return false;
        }

        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($this->isDirectory($file)) {
                $this->removeDirectory($file->getRealPath());
            } else {
                $this->remove($file->getRealPath());
            }
        }
        rmdir($dir);

        return true;
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
        if (!$this->isDirectory($from)) {
            return false;
        }

        $to = $this->convertStrToLocal($to);
        $from = $this->convertStrToLocal($from);
        $this->makeDirectory($to);
        $dir = opendir($from);
        if ($dir === false) {
            return false;
        }
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if ($this->isDirectory($from . '/' . $file)) {
                    $this->copyDirectory($from . '/' . $file, $to . '/' . $file);
                } else {
                    $this->copy($from . '/' . $file, $to . '/' . $file);
                    $this->changeMod($to . '/' . $file);
                }
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * ディレクトリを作成する
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        $dir = '';
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path); // Windows環境対策でディレクトリ区切り文字を”/”に統一
        foreach (preg_split("@(/)@", $path, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $token) {
            $dir .= $token;
            if (empty($dir)) {
                continue;
            }
            if ('/' === $token) {
                continue;
            }
            if (!$this->isDirectory($dir)) {
                mkdir($this->convertStrToLocal($dir));
                $this->changeMod($dir);
            }
        }
        return true;
    }

    /**
     * ファイルの更新日時を取得する
     *
     * @param string $path
     * @return int Unix time stamp
     */
    public function lastModified(string $path): int
    {
        $path = $this->convertStrToLocal($path);
        if ($this->exists($path)) {
            return filemtime($path);
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
        $iterator = new DirectoryIterator($path);
        $list = [];
        foreach ($iterator as $item) {
            if ($item->isDot() || $item->isDir()) {
                continue;
            }
            $list[] = $item->getPathname();
        }
        return $list;
    }

    /**
     * ブログ・年月を考慮したパスを取得
     *
     * @return string
     */
    public function archivesDir(): string
    {
        return sprintf('%03d', BID) . '/' . date('Ym') . '/';
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
        $source = $this->convertStrToLocal($source);
        $destination = $this->convertStrToLocal($destination);
        $root = $this->convertStrToLocal($root);
        $zippy = ZipExtensionAdapter::newInstance();

        if ($root) {
            $list = [$root => $source];
        } else {
            $list = [basename($destination, '.zip') => $source];
        }
        $archive = $zippy->create($destination, $list, true);
        foreach ($exclude as $path) {
            $archive->removeMembers($path);
        }
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
        $source = $this->convertStrToLocal($source);
        $destination = $this->convertStrToLocal($destination);
        $zippy = ZipExtensionAdapter::newInstance();
        $archive = $zippy->open($source);
        $archive->extract($destination);
    }

    /**
     * ユニークなファイルパスを取得
     *
     * @param string $original
     * @param string $prefix
     * @param int $num
     * @return string
     */
    public function uniqueFilePath(string $original, string $prefix = '', int $num = 0): string
    {
        if ($num > 0) {
            $name = pathinfo($original, PATHINFO_FILENAME);
            $extension = pathinfo($original, PATHINFO_EXTENSION);
            $dir = trim(dirname($original), '/') . '/';
            $path = "{$dir}{$name}_{$num}";
            if ($extension) {
                $path .= ".{$extension}";
            }
        } else {
            $path = $original;
        }
        if ($this->exists("{$prefix}{$path}")) {
            $num++;
            return $this->uniqueFilePath($original, $prefix, $num);
        } else {
            return $path;
        }
    }

    /**
     * ファイル名から不正な文字を削除
     *
     * @param string $source
     * @return string
     */
    public function removeIllegalCharacters(string $source): string
    {
        return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $source);
    }
}
