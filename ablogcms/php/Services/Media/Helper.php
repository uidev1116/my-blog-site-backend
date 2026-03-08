<?php

namespace Acms\Services\Media;

use Rhukster\DomSanitizer\DOMSanitizer;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Image;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\PrivateStorage;
use Acms\Services\Facades\Common;
use Acms\Services\Unit\UnitCollection;
use Acms\Services\Common\MimeTypeValidator;
use Acms\Services\Media\Enums\FileNameValidationError;
use SQL;
use SQL_Select;
use ACMS_RAM;
use ACMS_Hook;
use RuntimeException;
use InvalidArgumentException;

class Helper
{
    /**
     * メディアライブラリの利用が許可されているかどうかを確認
     * @param int $bid
     * @return bool
     */
    public function validate($bid = BID)
    {
        if (!IS_LICENSED) {
            return false;
        }
        if (config('media_library') !== 'on') {
            return false;
        }
        if (roleAvailableUser()) {
            if (!roleAuthorization('media_upload', $bid)) {
                return false;
            }
        } else {
            if (!sessionWithContribution($bid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * メディアを編集できるかどうかを確認
     * @param int $mid
     * @return bool
     */
    public function canEdit($mid)
    {
        if (sessionWithCompilation()) {
            return true;
        }
        if (isSessionContributor()) {
            $sql = SQL::newSelect('media');
            $sql->setSelect('media_user_id');
            $sql->addWhereOpr('media_id', $mid);
            $ownerId = DB::query($sql->get(dsn()), 'one');
            if (intval(SUID) === intval($ownerId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ファイル名のバリデーション(全角・半角両対応版)
     *
     * @param string $fileName 検証するファイル名（拡張子含む）
     * @return array{valid: true}|array{valid: false, error: string, errorCode: string}
     */
    public function validateFileName(string $fileName): array
    {
        // 入力値の前後の空白を除去
        $fileName = trim($fileName);

        // 1. 空文字チェック
        if ($fileName === '') {
            return ['valid' => false, 'error' => 'ファイル名を入力してください', 'errorCode' => FileNameValidationError::EMPTY->value];
        }

        // 2. ヌルバイトチェック
        if (strpos($fileName, "\0") !== false) {
            return ['valid' => false, 'error' => '不正な文字が含まれています', 'errorCode' => FileNameValidationError::NULL_BYTE->value];
        }

        // 3. パストラバーサル・パス区切り文字チェック
        if ($fileName !== PublicStorage::mbBasename($fileName)) {
            return ['valid' => false, 'error' => 'ファイル名にパス情報を含めることはできません', 'errorCode' => FileNameValidationError::PATH_TRAVERSAL->value];
        }

        // 4. 先頭・末尾のドット・句点チェック(半角・全角両方)
        if (preg_match('/^[\.。]|[\.。]$/u', $fileName)) {
            return ['valid' => false, 'error' => 'ファイル名の先頭または末尾にドットは使用できません', 'errorCode' => FileNameValidationError::LEADING_TRAILING_DOT->value];
        }

        // 5. 禁止文字チェック(Windows互換)
        // < > : " | ? *
        if (preg_match('/[<>:"|?*]/', $fileName)) {
            return ['valid' => false, 'error' => '使用できない文字が含まれています(< > : " | ? *)', 'errorCode' => FileNameValidationError::INVALID_CHARACTER->value];
        }

        // 6. 制御文字チェック(ASCII 0-31 および DEL 127)
        if (preg_match('/[\x00-\x1F\x7F]/', $fileName)) {
            return ['valid' => false, 'error' => '制御文字は使用できません', 'errorCode' => FileNameValidationError::INVALID_CHARACTER->value];
        }

        // 7. UTF-8エンコーディングチェック
        if (!mb_check_encoding($fileName, 'UTF-8')) {
            return ['valid' => false, 'error' => 'ファイル名のエンコーディングが不正です', 'errorCode' => FileNameValidationError::INVALID_ENCODING->value];
        }

        // 8. 文字数チェック（バイト数チェック）
        // MySQLのvarchar(255)はバイト数で制限されるため、strlen()を使用
        // mb_strlen()は文字数を返すため、日本語の場合にバイト数が255を超えても検証を通過してしまう
        if (strlen($fileName) > 255) {
            return ['valid' => false, 'error' => 'ファイル名が長すぎます(255バイト以内)', 'errorCode' => FileNameValidationError::TOO_LONG->value];
        }

        // 9. Windows予約語チェック
        $basename = pathinfo($fileName, PATHINFO_FILENAME);
        $reserved = [
            'CON', 'PRN', 'AUX', 'NUL',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
            'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'
        ];
        // 全角も含めて予約語チェック
        $basenameUpper = strtoupper(mb_convert_kana($basename, 'a', 'UTF-8')); // 全角英数を半角に変換
        if (in_array($basenameUpper, $reserved, true)) {
            return ['valid' => false, 'error' => 'この名前はシステムで予約されているため使用できません', 'errorCode' => FileNameValidationError::WINDOWS_RESERVED->value];
        }

        return ['valid' => true];
    }

    /**
     * メディアの基本情報を取得
     * @param array{name: string, tmp_name: string, type: string, size: int} $fileObj
     * @param string $tags
     * @return array{tags: string, name: string, file: array{name: string, tmp_name: string, type: string, size: int}, size: int, type: string, extension: string}
     */
    public function getBaseInfo($fileObj, $tags)
    {
        // 'blob'は特殊なケースなので検証をスキップ
        if ($fileObj['name'] !== 'blob') {
            $validation = $this->validateFileName($fileObj['name']);
            if (!$validation['valid']) {
                throw new InvalidArgumentException($validation['error']);
            }
        }
        $info = getimagesize($fileObj['tmp_name']);
        $mimeType = $info['mime'] ?? null;
        $extension = $mimeType ? Image::detectImageExtenstion($info['mime']) : '';

        return [
            'tags' => $tags,
            'name' => $fileObj['name'],
            'file' => $fileObj,
            'size' => $fileObj['size'],
            'type' => $fileObj['type'],
            'extension' => $extension,
        ];
    }

    /**
     * メディアをコピーして新しい画像ファイルを作成する
     * @param int $mid
     * @param string $filename
     * @return array{
     *   path: string,
     *   name: string,
     *   original: string
     * }
     */
    public function copyImages(int $mid, string $filename = ''): array
    {
        $oldData = $this->getMedia($mid);
        $oldPath = $oldData['path'];
        $filename = $filename ?: $oldData['name'];

        $validation = $this->validateFileName($filename);
        if (!$validation['valid']) {
            throw new InvalidArgumentException($validation['error']);
        }

        $oldPath = MEDIA_LIBRARY_DIR . $oldPath;
        $info = pathinfo($oldPath);
        $name = preg_replace('/\.[^.]*$/u', '', PublicStorage::mbBasename($filename));
        $name = preg_replace('/\s/u', '_', $name);
        $dir = empty($info['dirname']) ? '' : $info['dirname'] . '/';
        $ext = empty($info['extension']) ? '' : '.' . $info['extension'];

        $newPath = $dir . $name . $ext;
        $newPath = PublicStorage::uniqueFilePath($newPath, '');
        $newName = preg_replace("/(.+)(\.[^.]+$)/", "$1", PublicStorage::mbBasename($newPath));
        $this->copyFile($oldPath, $newPath, true);
        $this->copyFile(otherSizeImagePath($oldPath, 'large'), otherSizeImagePath($newPath, 'large'), true);
        $this->copyFile(otherSizeImagePath($oldPath, 'tiny'), otherSizeImagePath($newPath, 'tiny'), true);

        return [
            'path' => substr($newPath, strlen(MEDIA_LIBRARY_DIR)),
            'name' => $newName . $ext,
            'original' => substr($newPath, strlen(MEDIA_LIBRARY_DIR)),
        ];
    }

    /**
     * メディアをコピーして新しいファイルを作成する
     * @param int $mid
     * @param string $filename
     * @return array{
     *   path: string,
     *   name: string
     * }
     */
    public function copyFiles(int $mid, string $filename = ''): array
    {
        $oldData = $this->getMedia($mid);
        $oldPath = $oldData['path'];
        $status = $oldData['status'];
        $baseDir = $status ? MEDIA_STORAGE_DIR : MEDIA_LIBRARY_DIR;
        $storage = $status ? PrivateStorage::getInstance() : PublicStorage::getInstance();
        assert($storage instanceof \Acms\Services\Storage\Filesystem);
        $filename = $filename ?: $oldData['name'];

        $validation = $this->validateFileName($filename);
        if (!$validation['valid']) {
            throw new InvalidArgumentException($validation['error']);
        }

        $oldPath = $baseDir . $oldPath;
        $info = pathinfo($oldPath);
        $name = preg_replace('/\.[^.]*$/u', '', PublicStorage::mbBasename($filename));
        $name = preg_replace('/\s/u', '_', $name);
        $dir = empty($info['dirname']) ? '' : $info['dirname'] . '/';
        $ext = empty($info['extension']) ? '' : $info['extension'];

        $newPath = $dir . $name . '.' . $ext;
        $newPath = $storage->uniqueFilePath($newPath, '');
        $newName = preg_replace("/(.+)(\.[^.]+$)/", "$1", PublicStorage::mbBasename($newPath));
        $this->copyFile($oldPath, $newPath, !$status);

        return [
            'path' => substr($newPath, strlen($baseDir)),
            'name' => $newName . '.' . $ext,
        ];
    }

    /**
     * ファイルをコピー
     *
     * @param string $from
     * @param string $to
     * @param bool $isPublic
     * @return bool
     */
    function copyFile(string $from, string $to, bool $isPublic)
    {
        $res = copyFile($from, $to, $isPublic);

        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaCreate', $to);
        }
        return $res;
    }

    /**
     * 画像をアップロード
     * @param string $fieldName
     * @param bool $original
     * @return array{
     *   path: string,
     *   type: 'image',
     *   name: string,
     *   size: string,
     *   filesize: int,
     *   extension: string,
     * }
     */
    public function uploadImage($fieldName = 'file', $original = true)
    {
        if (!is_uploaded_file($_FILES[$fieldName]['tmp_name'])) {
            throw new \InvalidArgumentException('ファイルがアップロードされていません');
        }
        $filepath = $_FILES[$fieldName]['tmp_name'];
        $filename = $_FILES[$fieldName]['name'];

        /**
         * @var array{
         *  path: string,
         *  type: string,
         *  name: string,
         *  size: string
         * } $data
         */
        $data = $this->storeImage(
            filepath: $filepath,
            filename: $filename,
            angle: null,
            original: $original,
        );
        return [
            'path' => $data['path'],
            'type' => 'image',
            'name' => PublicStorage::mbBasename($data['path']),
            'size' => $data['size'],
            'filesize' => PublicStorage::getFileSize(MEDIA_LIBRARY_DIR . $data['path']),
            'extension' => $data['type'],
        ];
    }

    /**
     * PDFのサムネイルをアップロード
     * @param string $name
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string
     * }
     */
    public function uploadPdfThumbnail($name)
    {
        $filepath = $_FILES[$name]['tmp_name'];
        $filename = $_FILES[$name]['name'];
        if (!is_uploaded_file($filepath)) {
            throw new \InvalidArgumentException('ファイルがアップロードされていません');
        }
        return $this->storePdfThumbnailImage(
            filepath: $filepath,
            filename: $filename
        );
    }

    /**
     * SVGをアップロード
     * @param int $size
     * @param string $fieldName
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string,
     *   filesize: int,
     * }
     */
    public function uploadSvg($size, $fieldName = 'file')
    {
        $data = $this->createFile(MEDIA_LIBRARY_DIR, $fieldName, false);
        $data['extension'] = $data['type'];
        $data['type'] = 'svg';
        $data['size'] = '';
        $data['filesize'] = $size;

        return $data;
    }

    /**
     * ファイルをアップロード
     * @param int $size
     * @param string $fieldName
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string,
     *   filesize: int,
     *   extension: string,
     * }|false
     */
    public function uploadFile($size, $fieldName = 'file')
    {
        $data = $this->createFile(MEDIA_STORAGE_DIR, $fieldName, false);
        if (!PrivateStorage::exists(MEDIA_STORAGE_DIR . $data['path'])) {
            return false;
        }
        $data['extension'] = $data['type'];
        $data['type'] = 'file';
        $data['size'] = '';
        $data['filesize'] = $size;

        return $data;
    }

    /**
     * 画像ファイルを削除
     *
     * @param int $mid
     * @param bool $removeOriginal
     * @return void
     */
    public function deleteImage($mid, $removeOriginal = true)
    {
        $oldData = $this->getMedia($mid);
        if (!isset($oldData['path'])) {
            return;
        }
        $this->removeImageFiles($oldData['path'], $removeOriginal);
    }

    /**
     * サムネイル画像を削除
     *
     * @param int $mid
     * @return void
     */
    public function deleteThumbnail($mid)
    {
        $oldData = $this->getMedia($mid);
        if (!isset($oldData['thumbnail'])) {
            return;
        }
        $this->removeImageFiles($oldData['path'], true);
    }

    /**
     * ファイルを削除
     * @param int $mid
     * @return void
     */
    public function deleteFile($mid)
    {
        $oldData = $this->getMedia($mid);
        $status = $oldData['status'];
        $path = $oldData['path'];
        $baseDir = $status ? MEDIA_STORAGE_DIR : MEDIA_LIBRARY_DIR;
        $storage = $status ? PrivateStorage::getInstance() : PublicStorage::getInstance();
        assert($storage instanceof \Acms\Services\Storage\Filesystem);

        $storage->remove($baseDir . $path);
        Image::deleteImageAllSize(MEDIA_LIBRARY_DIR . $oldData['thumbnail']);
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaDelete', $baseDir . $path);
        }
    }

    /**
     * ファイルをリネーム
     * @param array{
     *  path: string,
     *  type: 'image' | 'svg' | 'file',
     *  name: string,
     *  extension: string,
     *  original: string,
     * } $data
     * @param string $rename
     * @return array
     */
    public function rename($data, $rename)
    {
        if ($data['name'] === $rename) {
            return $data;
        }
        $validation = $this->validateFileName($rename);
        if (!$validation['valid']) {
            throw new InvalidArgumentException($validation['error']);
        }
        $type = $data['type'];
        $basename = preg_replace("/(.+)(\.[^.]+$)/", "$1", $rename) . '.' . strtolower($data['extension']);

        $path = $data['path'];
        $renamePath = trim(dirname($path), '/') . '/' . $basename;
        if ($type === 'image' || $type === 'svg') {
            $renamePath = PublicStorage::uniqueFilePath($renamePath, MEDIA_LIBRARY_DIR); // 名前の重複を避ける
        } elseif ($type === 'file') {
            $renamePath = PrivateStorage::uniqueFilePath($renamePath, MEDIA_STORAGE_DIR); // 名前の重複を避ける
        }
        $data['name'] = PublicStorage::mbBasename($renamePath);
        $data['path'] = $renamePath;

        if ($type === 'image') {
            $normalPath = $path;
            foreach (['normal', 'large', 'tiny', 'square'] as $imageType) {
                $fromPath = otherSizeImagePath($normalPath, $imageType);
                $toPath = otherSizeImagePath($renamePath, $imageType);
                PublicStorage::move(MEDIA_LIBRARY_DIR . $fromPath, MEDIA_LIBRARY_DIR . $toPath);
                PublicStorage::move(MEDIA_LIBRARY_DIR . $fromPath . '.webp', MEDIA_LIBRARY_DIR . $toPath . '.webp');
            }
            $data['original'] = otherSizeImagePath($renamePath, 'large');

            // mode_xxxxファイルを削除
            $cacheImagePath = trim(dirname(MEDIA_LIBRARY_DIR . $path), '/') . '/*-' . PublicStorage::mbBasename($path);
            $cacheImages = glob($cacheImagePath);
            if (is_array($cacheImages)) {
                foreach ($cacheImages as $filename) {
                    if (preg_match('/(tiny|large|square)-(.*)$/', $filename)) {
                        continue;
                    }
                    PublicStorage::remove($filename);
                    PublicStorage::remove($filename . '.webp');
                }
            }
        } elseif ($type === 'svg') {
            PublicStorage::move(MEDIA_LIBRARY_DIR . $path, MEDIA_LIBRARY_DIR . $renamePath);
            $data['original'] = $renamePath;
        } elseif ($type === 'file') {
            PrivateStorage::move(MEDIA_STORAGE_DIR . $path, MEDIA_STORAGE_DIR . $renamePath);
        }
        return $data;
    }

    /**
     * パスをURLエンコード
     *
     * @param string $url
     * @return string
     */
    public function urlencode(string $url): string
    {
        // parse_urlは相対URLにもある程度対応
        $parts = parse_url($url);

        // パス部分処理
        if ($parts['path'] ?? null) {
            $pathParts = explode('/', $parts['path']);
            $filename = array_pop($pathParts);
            $encodedFilename = rawurlencode($filename);
            $encodedPath = implode('/', $pathParts) . '/' . $encodedFilename;
            // パス先頭のスラッシュ考慮
            if (substr($parts['path'], 0, 1) === '/') {
                $encodedPath = '/' . ltrim($encodedPath, '/');
            }
        } else {
            $encodedPath = '';
        }

        // クエリ部分処理
        $encodedQuery = '';
        if ($parts['query'] ?? null) {
            parse_str($parts['query'], $queryArray);
            $encodedQuery = http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);
        }

        // 組み立て
        $result = '';
        if (($parts['scheme'] ?? null) && ($parts['host'] ?? null)) {
            $result .= $parts['scheme'] . '://';
            if ($parts['user'] ?? null) {
                $result .= $parts['user'];
                if ($parts['pass'] ?? null) {
                    $result .= ':' . $parts['pass'];
                }
                $result .= '@';
            }
            $result .= $parts['host'];
            if ($parts['port'] ?? null) {
                $result .= ':' . $parts['port'];
            }
        }
        $result .= $encodedPath;
        if ($encodedQuery !== '') {
            $result .= '?' . $encodedQuery;
        }
        if ($parts['fragment'] ?? null) {
            $result .= '#' . rawurlencode($parts['fragment']);
        }

        return $result;
    }

    /**
     * キャッシュバスティング
     * @param string $updated
     * @return string
     */
    public function cacheBusting(string $updated): string
    {
        return '?v=' . date('YmdHis', strtotime($updated));
    }

    /**
     * メディアタイプが画像かどうか
     * @param string $type
     * @return bool
     */
    public function isImageFile($type)
    {
        return preg_match('/^image/', $type) && !preg_match('/svg/', $type);
    }

    /**
     * メディアタイプがSVGかどうか
     * @param string $type
     * @return bool
     */
    public function isSvgFile($type)
    {
        return preg_match('/svg/', $type) === 1;
    }

    /**
     * メディアタイプがファイルかどうか
     * @param string $type
     * @return bool
     */
    public function isFile($type)
    {
        return preg_match('/^file/', $type) === 1;
    }

    /**
     * 編集されたアイコンのパスを取得
     *
     * @param string $path
     * @return string
     */
    public function getEditedIcon(string $path): string
    {
        return Common::resolveUrl('/' . DIR_OFFSET . $path);
    }

    /**
     * 画像のCMS設置ディレクトリの相対パスを取得
     * @param string $path
     * @return string
     */
    public function getImagePath($path)
    {
        return Common::resolveUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path);
    }

    /**
     * 画像のサムネイルパスを取得
     * @param string $path
     * @return string
     */
    public function getImageThumbnail($path)
    {
        return Common::resolveUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . otherSizeImagePath($path, 'tiny'));
    }

    /**
     * SVGのサムネイルパスを取得
     * @param string $path
     * @return string
     */
    public function getSvgThumbnail($path)
    {
        return Common::resolveUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path);
    }

    /**
     * ファイルのサムネイルパスを取得
     * @param string $extension
     * @return string
     */
    public function getFileThumbnail($extension)
    {
        return Common::resolveUrl('/' . DIR_OFFSET . pathIcon($extension));
    }

    /**
     * PDFのサムネイルパスを取得
     * @param string $path
     * @return string
     */
    public function getPdfThumbnail($path)
    {
        return Common::resolveUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path);
    }

    /**
     * 画像のパーマリンクを取得
     * @param string $path
     * @return string
     */
    public function getImagePermalink($path)
    {
        return Common::resolveUrl(BASE_URL . MEDIA_LIBRARY_DIR . $path);
    }

    /**
     * ファイルのパーマリンクを取得
     * @param int $mid
     * @param bool $fullpath
     * @return string
     */
    public function getFilePermalink($mid, $fullpath = true)
    {
        if ($fullpath) {
            return acmsLink(['bid' => BID], false) . MEDIA_FILE_SEGMENT . '/' . $mid . '/' . $this->getDownloadLinkHash($mid) . '/' . ACMS_RAM::mediaExtension($mid) . '/';
        }
        $offset = rtrim(DIR_OFFSET . acmsPath(['bid' => BID]), '/');
        if (strlen($offset) > 0) {
            $offset .= '/';
        }
        $newPath = '/' . $offset .  MEDIA_FILE_SEGMENT . '/' . $mid . '/' . $this->getDownloadLinkHash($mid) . '/' . ACMS_RAM::mediaExtension($mid) . '/';
        return Common::resolveUrl($newPath);
    }

    /**
     * ダウンロードリンクハッシュを取得
     * @param int $mid
     * @return string
     */
    public function getDownloadLinkHash($mid)
    {
        $pepper = sha1('pepper' . PASSWORD_SALT_1);
        $hash = sha1($pepper . $mid . PASSWORD_SALT_1);

        return substr($hash, 0, 16);
    }

    /**
     * ファイルの古いパーマリンクを取得
     * @param string $path
     * @param bool $fullpath
     * @return string
     */
    public function getFileOldPermalink($path, $fullpath = true)
    {
        if ($fullpath) {
            return Common::resolveUrl(BASE_URL . MEDIA_LIBRARY_DIR . $path);
        }
        return Common::resolveUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path);
    }

    /**
     * オリジナル画像のパスを取得
     * @param string $original
     * @return string
     */
    public function getOriginal($original)
    {
        if ($original && !PublicStorage::exists(MEDIA_LIBRARY_DIR . $original)) {
            $original = '';
        }
        if (empty($original)) {
            return '';
        }
        return Common::resolveUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $original);
    }

    /**
     * タグをフィルタリング
     * @param \SQL_Select $SQL
     * @param string[] $tags
     * @return false|void
     */
    public function filterTag($SQL, $tags)
    {
        if (!is_array($tags) and empty($tags)) {
            return false;
        }

        $tag = array_shift($tags);
        $SQL->addLeftJoin('media_tag', 'media_tag_media_id', 'media_id', 'tag0');
        $SQL->addWhereOpr('media_tag_name', $tag, '=', 'AND', 'tag0');
        $i  = 1;
        while ($tag = array_shift($tags)) {
            $SQL->addLeftJoin('media_tag', 'media_tag_media_id', 'media_tag_media_id', 'tag' . $i, 'tag' . ($i - 1));
            $SQL->addWhereOpr('media_tag_name', $tag, '=', 'AND', 'tag' . $i);
            $i++;
        }
    }

    /**
     * タグを保存
     * @param int $mid
     * @param string $tags
     * @param int $bid
     * @return void
     */
    public function saveTags($mid, $tags, $bid = BID)
    {
        $SQL = SQL::newDelete('media_tag');
        $SQL->addWhereOpr('media_tag_media_id', $mid);
        DB::query($SQL->get(dsn()), 'exec');

        $tags = Common::getTagsFromString($tags);

        $insert = SQL::newBulkInsert('media_tag');
        foreach ($tags as $sort => $tag) {
            $insert->addInsert([
                'media_tag_name' => $tag,
                'media_tag_sort' => $sort + 1,
                'media_tag_media_id' => $mid,
                'media_tag_blog_id' => $bid,
            ]);
        }
        if ($insert->hasData()) {
            DB::query($insert->get(dsn()), 'exec');
        }
    }

    /**
     * タグを削除
     * @param string $tagName
     * @param int $bid
     * @return void
     */
    public function deleteTag($tagName, $bid = BID)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('media_tag');
        $SQL->addWhereOpr('media_tag_name', $tagName);
        $SQL->addWhereOpr('media_tag_blog_id', $bid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * タグを更新
     * @param string $oldTag
     * @param string $newTag
     * @param int $bid
     * @return void
     */
    public function updateTag($oldTag, $newTag, $bid = BID)
    {
        $DB = DB::singleton(dsn());
        $SQL    = SQL::newSelect('media_tag');
        $SQL->setSelect('media_tag_media_id');
        $SQL->addWhereIn('media_tag_name', [$oldTag, $newTag]);
        $SQL->addWhereOpr('media_tag_blog_id', BID);
        $SQL->setGroup('media_tag_media_id');
        $SQL->setHaving(SQL::newOpr('media_tag_media_id', 2, '>=', null, 'COUNT'));
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        if ($statement && ($row = $DB->next($statement))) {
            do {
                $eid = intval($row['media_tag_media_id']);
                $Del = SQL::newDelete('media_tag');
                $Del->addWhereOpr('media_tag_name', $newTag);
                $Del->addWhereOpr('media_tag_media_id', $eid);
                $Del->addWhereOpr('media_tag_blog_id', BID);
                $DB->query($Del->get(dsn()), 'exec');
            } while ($row = $DB->next($statement));
        }

        $SQL = SQL::newUpdate('media_tag');
        $SQL->setUpdate('media_tag_name', $newTag);
        $SQL->addWhereOpr('media_tag_name', $oldTag);
        $SQL->addWhereOpr('media_tag_blog_id', $bid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * メディアを挿入
     * @param int $mid
     * @param array $data
     * @return void
     */
    public function insertMedia($mid, $data)
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newInsert('media');
        $SQL->addInsert('media_id', $mid);
        $SQL->addInsert('media_type', $data['type']);
        $SQL->addInsert('media_extension', $data['extension']);
        $SQL->addInsert('media_path', $data['path']);
        $SQL->addInsert('media_file_name', $data['name']);
        $SQL->addInsert('media_file_size', $data['filesize']);
        $SQL->addInsert('media_image_size', $data['size']);
        if (isset($data['thumbnail'])) {
            $SQL->addInsert('media_thumbnail', $data['thumbnail']);
        }
        if ($data['type'] === 'file') {
            if (isset($data['status']) && $data['status']) {
                $SQL->addInsert('media_status', $data['status']);
            } else {
                $SQL->addInsert('media_status', config('media_default_status', 'entry'));
            }
        } else {
            $SQL->addInsert('media_original', otherSizeImagePath($data['path'], 'large'));
        }
        foreach (['1', '2', '3', '4', '5', '6'] as $i) {
            if (isset($data['field_' . $i])) {
                $SQL->addInsert('media_field_' . $i, $data['field_' . $i]);
            }
        }
        $SQL->addInsert('media_upload_date', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('media_update_date', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('media_last_update_user_id', SUID);
        $SQL->addInsert('media_user_id', SUID);
        $SQL->addInsert('media_blog_id', BID);

        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * メディアを更新
     * @param int $mid
     * @param array $data
     * @return void
     */
    public function updateMedia($mid, $data)
    {
        $DB = DB::singleton(dsn());
        if (isset($data['original'])) {
            $old = loadMedia($mid);
            if ($old->get('original') !== $data['original']) {
                // オリジナル画像を更新する場合は古いファイルを削除
                if ($old->get('status')) {
                    PrivateStorage::remove($old->get('original'));
                } else {
                    PublicStorage::remove($old->get('original'));
                }
            }
        }
        $field = [
            'type' => 'media_type',
            'status' => 'media_status',
            'extension' => 'media_extension',
            'path' => 'media_path',
            'original' => 'media_original',
            'name' => 'media_file_name',
            'filesize' => 'media_file_size',
            'size' => 'media_image_size',
            'update_date' => 'media_update_date',
            'last_update_user_id' => 'media_last_update_user_id',
            'thumbnail' => 'media_thumbnail',
            'field_1' => 'media_field_1',
            'field_2' => 'media_field_2',
            'field_3' => 'media_field_3',
            'field_4' => 'media_field_4',
            'field_5' => 'media_field_5',
            'field_6' => 'media_field_6',
        ];
        $SQL = SQL::newUpdate('media');
        foreach ($field as $key => $column) {
            if (isset($data[$key])) {
                $SQL->addUpdate($column, $data[$key]);
            }
        }
        $SQL->addWhereOpr('media_id', $mid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * JSONをビルド
     * @param int $mid
     * @param array $data
     * @param string $tags
     * @param int $bid
     * @return array{
     *   media_status: string,
     *   media_title: string,
     *   media_label: string,
     *   media_last_modified: string,
     *   media_datetime: string,
     *   media_id: int,
     *   media_bid: int,
     *   media_blog_name: string,
     *   media_user_id: int,
     *   media_user_name: string,
     *   media_last_update_user_id: int|'',
     *   media_last_update_user_name: string,
     *   media_size: string,
     *   media_filesize: int,
     *   media_path: string,
     *   media_edited: string,
     *   media_original: string,
     *   media_thumbnail: string,
     *   media_permalink: string,
     *   media_type: string,
     *   media_ext: string,
     *   media_caption: string,
     *   media_link: string,
     *   media_alt: string,
     *   media_text: string,
     *   media_focal_point: string,
     *   media_editable: bool,
     *   media_pdf_page: string,
     *   checked: false
     * }
     */
    public function buildJson($mid, $data, $tags, $bid = BID)
    {
        $path = $data['path'];
        $type = $data['type'];
        $extension = $data['extension'];
        $original = '';
        $edited = '';
        $rootPath = '';
        $iconFullPath = '';
        $iconWidth = '';
        $iconHeight = '';

        if ($type === 'file') {
            if (empty($data['status'])) {
                $permalink = $this->getFileOldPermalink($path);
            } else {
                $permalink = $this->getFilePermalink($mid);
            }
            if (strtolower($extension) === 'pdf' && $data['thumbnail']) {
                $thumbnail = $this->getPdfThumbnail($data['thumbnail']);
            } else {
                $thumbnail = $this->getFileThumbnail($extension);
            }
            $iconPath = pathIcon($extension);
            $iconFullPath = $this->getEditedIcon($iconPath);
            $iconDimensions = Image::getImageDimensions($iconPath);
            $iconWidth = $iconDimensions['width'] ?? '';
            $iconHeight = $iconDimensions['height'] ?? '';
        } else {
            $edited = $this->getImagePath($path);
            $permalink = $this->getImagePermalink($path);
            $original = $this->urlencode($this->getOriginal($data['original']));
            if ($type === 'svg') {
                $thumbnail = $this->getSvgThumbnail($path);
            } else {
                $thumbnail = $this->getImageThumbnail($path);
            }
            $thumbnail = $thumbnail . $this->cacheBusting($data['update_date']);
            $rootPath = $this->getImagePath($path) . $this->cacheBusting($data['update_date']);
        }
        return [
            "media_status" => $data['status'],
            "media_title" => $data['name'],
            "media_label" => $tags,
            "media_last_modified" => $data['update_date'],
            "media_datetime" => $data['upload_date'],
            "media_id" => intval($mid),
            "media_bid" => intval($bid),
            "media_blog_name" => isset($data['blog_name']) ? $data['blog_name'] : ACMS_RAM::blogName($bid),
            "media_user_id" => intval($data['user_id']),
            "media_user_name" => $data['user_name'],
            'media_last_update_user_id' => isset($data['last_update_user_id']) ? intval($data['last_update_user_id']) : '',
            'media_last_update_user_name' => isset($data['last_update_user_name']) ? $data['last_update_user_name'] : '',
            "media_size" => $data['size'],
            "media_filesize" => intval($data['filesize']),
            "media_path" => $this->urlencode($path),
            "media_root_path" => $rootPath,
            "media_edited" => $edited,
            "media_original" => $original,
            "media_thumbnail" => $thumbnail,
            "media_permalink" => $permalink,
            'media_icon' => $iconFullPath,
            'media_icon_width' => $iconWidth,
            'media_icon_height' => $iconHeight,
            "media_type" => $type,
            "media_ext" => $extension,
            "media_caption" => isset($data['field_1']) ? $data['field_1'] : '',
            "media_link" => isset($data['field_2']) ? $data['field_2'] : '',
            "media_alt" => isset($data['field_3']) ? $data['field_3'] : '',
            "media_text" => isset($data['field_4']) ? $data['field_4'] : '',
            "media_focal_point" => isset($data['field_5']) ? $data['field_5'] : '',
            "media_editable" => isset($data['editable']) ? $data['editable'] : false,
            "media_pdf_page" => isset($data['field_6']) ? $data['field_6'] : '',
            "checked" => false
        ];
    }

    /**
     * メディアのアーカイブリストを取得
     * @param \SQL $sql
     * @return string[]
     */
    public function getMediaArchiveList($sql)
    {
        $archives = [];
        $archive = new SQL_Select($sql);
        $archive->addSelect('media_upload_date');
        $archive->addSelect(SQL::newFunction('media_upload_date', ['SUBSTR', 0, 7]), 'media_date');
        $archive->addGroup('media_date');
        $all = DB::query($archive->get(dsn()), 'all');
        foreach ($all as $row) {
            $archives[] = $row['media_date'];
        }
        return $archives;
    }

    /**
     * メディアのタグリストを取得
     * @param \SQL $sql
     * @return string[]
     */
    public function getMediaTagList($sql)
    {
        $tags = [];
        $tag = new SQL_Select($sql);
        $tag->addLeftJoin('media_tag', 'media_tag_media_id', 'media_id');
        $tag->addGroup('media_tag_name');
        $all = DB::query($tag->get(dsn()), 'all');
        foreach ($all as $row) {
            if ($row['media_tag_name']) {
                $tags[] = $row['media_tag_name'];
            }
        }
        return $tags;
    }

    /**
     * メディアの拡張子リストを取得
     * @param \SQL $sql
     * @return string[]
     */
    public function getMediaExtensionList($sql)
    {
        $exts = [];
        $ext = new SQL_Select($sql);
        $ext->addGroup('media_extension');
        $all = DB::query($ext->get(dsn()), 'all');
        foreach ($all as $row) {
            if ($row['media_extension']) {
                $exts[] = $row['media_extension'];
            }
        }
        return $exts;
    }

    /**
     * メディアのラベルを取得
     * @param int $mid
     * @return string
     */
    public function getMediaLabel($mid)
    {
        $label = '';
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('media_tag');
        $SQL->addSelect('media_tag_name');
        $SQL->addWhereOpr('media_tag_media_id', $mid);
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        while ($row = $DB->next($statement)) {
            if ($label) {
                $label = $label . ',' . $row['media_tag_name'];
            } else {
                $label = $row['media_tag_name'];
            }
        }
        return $label;
    }

    /**
     * メディアIDからメディア情報を取得
     *
     * @param int[] $midiaIds
     * @return array<int, array<string, mixed>>
     */
    public function mediaEagerLoad(array $midiaIds): array
    {
        $mediaDataList = [];
        if ($midiaIds) {
            $SQL = SQL::newSelect('media');
            $SQL->addWhereIn('media_id', $midiaIds);
            $q = $SQL->get(dsn());
            $DB = DB::singleton(dsn());
            $statement = $DB->query($q, 'exec');
            while ($media = $DB->next($statement)) {
                $mediaId = intval($media['media_id']);
                $mediaDataList[$mediaId] = $media;
            }
        }
        return $mediaDataList;
    }

    /**
     * ユニットモデル一覧からメディア情報を取得
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @return array<int, array<string, mixed>>
     */
    public function mediaEagerLoadFromUnit(UnitCollection $collection): array
    {
        $mediaList = [];
        foreach ($collection->flat() as $unit) {
            if ($unit instanceof \Acms\Services\Unit\Models\Media) {
                $mediaData = $unit->getField1();
                if (empty($mediaData)) {
                    continue;
                }
                $mediaAry = $unit->explodeUnitDataTrait($mediaData);
                foreach ($mediaAry as $i => $mediaId) {
                    $mediaList[] = $mediaId;
                }
            }
        }
        return $this->mediaEagerLoad($mediaList);
    }

    /**
     * メディアIDから整形されたメディア一覧を取得
     *
     * @param int[] $midiaIds
     * @return array{
     *  path: string,
     *  width: string,
     *  height: string,
     *  permalink: string,
     *  icon: string,
     *  iconWidth: string,
     *  iconHeight: string,
     *  extension: string,
     *  fileSize: int,
     * }[]
     */
    public function getMediaList(array $midiaIds): array
    {
        $mediaList = $this->mediaEagerLoad($midiaIds);

        return array_map(function ($media) {
            $path = $media['media_path'];
            [$width, $height] = array_pad(explode('x', $media['media_image_size']), 2, '');
            $width = '';
            $height = '';
            $permalink = '';
            $iconFullPath = '';
            $iconWidth = '';
            $iconHeight = '';
            if ($media['media_image_size']) {
                [$width, $height] = array_pad(explode('x', $media['media_image_size']), 2, '');
                $width = (string) $width;
                $height = (string) $height;
            }
            if ($media['media_type'] === 'file') {
                if ($media['media_status']) {
                    $permalink = $this->getFilePermalink($media['media_id']);
                } else {
                    $permalink = $this->getFileOldPermalink($path);
                }
                $iconPath = pathIcon($media['media_extension']);
                $iconFullPath = $this->getEditedIcon($iconPath);
                $iconDimensions = Image::getImageDimensions($iconPath);
                $iconWidth = $iconDimensions['width'] ?? '';
                $iconHeight = $iconDimensions['height'] ?? '';
            }
            return [
                'path' => $this->getImagePath($path) . $this->cacheBusting($media['media_update_date']),
                'width' => $width,
                'height' => $height,
                'permalink' => $permalink,
                'icon' => $iconFullPath,
                'iconWidth' => $iconWidth,
                'iconHeight' => $iconHeight,
                'extension' => $media['media_extension'],
                'fileSize' => $media['media_file_size'],
            ];
        }, $mediaList);
    }

    /**
     * メディアを取得
     * @param int $mid
     * @return array{
     *   mid: int,
     *   bid: int,
     *   status: string,
     *   path: string,
     *   thumbnail: string,
     *   name: string,
     *   size: string,
     *   filesize: int,
     *   type: string,
     *   extension: string,
     *   original: string,
     *   update_date: string,
     *   upload_date: string,
     *   field_1: string,
     *   field_2: string,
     *   field_3: string,
     *   field_4: string,
     *   field_5: string,
     *   field_6: string,
     *   blog_name: string,
     *   user_id: int,
     *   user_name: string,
     *   last_update_user_id: int,
     *   last_update_user_name: string,
     *   editable: bool
     * }|array{}
     */
    public function getMedia($mid)
    {
        $sql = SQL::newSelect('media', 'm');
        foreach (
            [
                [
                    'field' => '*',
                    'alias' => null,
                    'scope' => 'm',
                    'function' => null
                ],
                [
                    'field' => 'user_name',
                    'alias' => 'user_name',
                    'scope' => 'user',
                    'function' => null
                ],
                [
                    'field' => 'user_name',
                    'alias' => 'last_update_user_name',
                    'scope' => 'last_update_user',
                    'function' => null
                ],
                [
                    'field' => 'blog_name',
                    'alias' => null,
                    'scope' => null,
                    'function' => null
                ],
            ] as $select
        ) {
            $sql->addSelect(
                $select['field'],
                $select['alias'],
                $select['scope'],
                $select['function']
            );
        }

        $sql->addLeftJoin('blog', 'blog_id', 'media_blog_id');
        $sql->addLeftJoin('user', 'user_id', 'media_user_id', 'user');
        $sql->addLeftJoin('user', 'user_id', 'media_last_update_user_id', 'last_update_user');
        $sql->addWhereOpr('media_id', $mid);
        $row = DB::query($sql->get(dsn()), 'row');
        if (empty($row)) {
            return [];
        }

        return [
            'mid' => $row['media_id'],
            'bid' => $row['media_blog_id'],
            'status' => $row['media_status'],
            'path' => $row['media_path'],
            'thumbnail' => $row['media_thumbnail'],
            'name' => $row['media_file_name'],
            'size' => $row['media_image_size'],
            'filesize' => $row['media_file_size'],
            'type' => $row['media_type'],
            'extension' => $row['media_extension'],
            'original' => $row['media_original'],
            'update_date' => $row['media_update_date'],
            'upload_date' => $row['media_upload_date'],
            'field_1' => $row['media_field_1'],
            'field_2' => $row['media_field_2'],
            'field_3' => $row['media_field_3'],
            'field_4' => $row['media_field_4'],
            'field_5' => $row['media_field_5'],
            'field_6' => $row['media_field_6'],
            'blog_name' => $row['blog_name'],
            'user_id' => $row['media_user_id'],
            'user_name' => $row['user_name'],
            'last_update_user_id' => $row['media_last_update_user_id'],
            'last_update_user_name' => $row['last_update_user_name'],
            'editable' => intval($row['media_user_id']) === SUID
        ];
    }

    /**
     * メディアの削除
     *
     * @param int $mid
     * @return void
     */
    public function deleteItem($mid)
    {
        $DB = DB::singleton(dsn());
        try {
            if (empty($mid) || !self::canEdit($mid)) {
                throw new \RuntimeException('You are not authorized to delete media.');
            }
            $SQL = SQL::newSelect('media');
            $SQL->addWhereOpr('media_id', $mid);
            $q = $SQL->get(dsn());
            $statement = $DB->query($q, 'exec');
            while ($row = $DB->next($statement)) {
                $type = $row['media_type'];
                if ($type === 'image') {
                    $path = MEDIA_LIBRARY_DIR . $row['media_path'];
                    $original = MEDIA_LIBRARY_DIR . $row['media_original'];
                    Image::deleteImageAllSize($path);
                    Image::deleteImageAllSize($original);
                } else {
                    self::deleteFile($mid);
                }
            }
            $DB = DB::singleton(dsn());
            $SQL = SQL::newDelete('media');
            $SQL->addWhereOpr('media_id', $mid);
            $DB->query($SQL->get(dsn()), 'exec');

            $SQL = SQL::newDelete('media_tag');
            $SQL->addWhereOpr('media_tag_media_id', $mid);
            $DB->query($SQL->get(dsn()), 'exec');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * メディアフィールドの挿入
     *
     * @param \Field $Field
     * @param array $mediaList
     * @param string[] $useMediaField
     * @return void
     */
    public function injectMediaField($Field, $mediaList, $useMediaField)
    {
        $useMediaField = array_unique($useMediaField);
        foreach ($useMediaField as $fd) {
            $sourceField = $Field->getArray($fd . '@media');
            $nameAry = [];
            $extensionAry = [];
            $fileSizeAry = [];
            $captionAry = [];
            $linkAry = [];
            $altAry = [];
            $textAry = [];
            $pathAry = [];
            $thumbnailAry = [];
            $pageAry = [];
            $imageSizeAry = [];
            $widthAry = [];
            $heightAry = [];
            $ratioAry = [];
            $focalXAry = [];
            $focalYAry = [];
            $typeAry = [];

            foreach ($sourceField as $i => $mid) {
                if (isset($mediaList[$mid])) {
                    $media = $mediaList[$mid];
                    $path = $media['media_path'];
                    $type = $media['media_type'];

                    $nameAry[] = $media['media_file_name'];
                    $extensionAry[] = $media['media_extension'];
                    $fileSizeAry[] = $media['media_file_size'];
                    $captionAry[] = $media['media_field_1'];
                    $linkAry[] = $media['media_field_2'];
                    $altAry[] = $media['media_field_3'];
                    $textAry[] = $media['media_field_4'];
                    $pageAry[] = $media['media_field_6'];
                    $typeAry[] = $type;

                    if ($type === 'image') {
                        $path .= $this->cacheBusting($media['media_update_date']);
                        $pathAry[] = Common::resolveUrl($path, MEDIA_LIBRARY_DIR);
                        $thumbnailAry[] = $this->getImageThumbnail($path);
                        $imageSizeAry[] = $media['media_image_size'];
                        $focalPoint = $media['media_field_5'];
                        $tmpFocalX = '';
                        $tmpFocalY = '';
                        $width = '';
                        $height = '';
                        $ratio = '';

                        if ($media['media_image_size']) {
                            list($w, $h) = explode('x', $media['media_image_size']);
                            $w = intval(trim($w), 10);
                            $h = intval(trim($h), 10);
                            if ($w > 0 && $h > 0) {
                                $width = $w;
                                $height = $h;
                                $ratio = round($w / $h, 2);
                            }
                        }
                        if (strpos($focalPoint, ',') !== false) {
                            list($focalX, $focalY) = explode(',', $focalPoint);
                            if ($focalX && $focalY) {
                                $tmpFocalX = ((float)$focalX / 50) - 1;
                                $tmpFocalY = (((float)$focalY / 50) - 1) * -1;
                            }
                        }
                        $focalXAry[] = $tmpFocalX;
                        $focalYAry[] = $tmpFocalY;
                        $widthAry[] = $width;
                        $heightAry[] = $height;
                        $ratioAry[] = $ratio;
                    } elseif ($type === 'svg') {
                        $path .= $this->cacheBusting($media['media_update_date']);
                        $pathAry[] = Common::resolveUrl($path, MEDIA_LIBRARY_DIR);
                        $thumbnailAry[] = $this->getSvgThumbnail($path);
                        $imageSizeAry[] = '';
                        $focalXAry[] = '';
                        $focalYAry[] = '';
                        $widthAry[] = '';
                        $heightAry[] = '';
                        $ratioAry[] = '';
                    } elseif ($type === 'file') {
                        if (empty($media['media_status'])) {
                            $pathAry[] = $this->getFileOldPermalink($path, false);
                        } else {
                            $pathAry[] = $this->getFilePermalink($mid, false);
                        }
                        if (strtolower($media['media_extension']) === 'pdf' && $media['media_thumbnail']) {
                            $thumbnailAry[] = $this->getPdfThumbnail($media['media_thumbnail']);
                        } else {
                            $thumbnailAry[] = $this->getFileThumbnail($media['media_extension']);
                        }
                        $imageSizeAry[] = '';
                        $focalXAry[] = '';
                        $focalYAry[] = '';
                        $widthAry[] = '';
                        $heightAry[] = '';
                        $ratioAry[] = '';
                    }
                } else {
                    $nameAry[] = '';
                    $extensionAry[] = '';
                    $fileSizeAry[] = '';
                    $captionAry[] = '';
                    $linkAry[] = '';
                    $altAry[] = '';
                    $textAry[] = '';
                    $pathAry[] = '';
                    $thumbnailAry[] = '';
                    $pageAry[] = '';
                    $imageSizeAry[] = '';
                    $focalXAry[] = '';
                    $focalYAry[] = '';
                    $widthAry[] = '';
                    $heightAry[] = '';
                    $ratioAry[] = '';
                    $typeAry[] = '';
                }
            }
            $Field->setField($fd . '@name', $nameAry);
            $Field->setField($fd . '@extension', $extensionAry);
            $Field->setField($fd . '@fileSize', $fileSizeAry);
            $Field->setField($fd . '@caption', $captionAry);
            $Field->setField($fd . '@link', $linkAry);
            $Field->setField($fd . '@alt', $altAry);
            $Field->setField($fd . '@text', $textAry);
            $Field->setField($fd . '@path', $pathAry);
            $Field->setField($fd . '@thumbnail', $thumbnailAry);
            $Field->setField($fd . '@page', $pageAry);
            $Field->setField($fd . '@imageSize', $imageSizeAry);
            $Field->addField($fd . '@focalX', $focalXAry);
            $Field->addField($fd . '@focalY', $focalYAry);
            $Field->addField($fd . '@width', $widthAry);
            $Field->addField($fd . '@height', $heightAry);
            $Field->addField($fd . '@ratio', $ratioAry);
            $Field->addField($fd . '@type', $typeAry);
        }
    }

    /**
     * メディアファイルのダウンロード
     *
     * @param int $mid
     * @return never|void
     */
    public function downloadFile($mid)
    {
        $media = $this->getMedia($mid);
        $download = new Download($media);
        if (!$download->exists()) {
            httpStatusCode('404 Not Found');
            return;
        }
        if (!$download->validate()) {
            httpStatusCode('403 Forbidden Media');
        } else {
            $download->download();
        }
    }

    /**
     * SVG（テキスト）をサニタイズ
     *
     * @param string $input
     * @return string
     */
    public function sanitizeSvg(string $input): string
    {
        $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
        return $sanitizer->sanitize($input);
    }

    /**
     * $_FILES のフィールドからファイルをアップロードする
     *
     * @param string $archivesDir
     * @param string $fieldName
     * @param bool $random
     * @return array{path: string, type: string, name: string, size: string}
     * @throws RuntimeException
     */
    protected function createFile(string $archivesDir, string $fieldName = 'file', bool $random = true): array
    {
        if (!isset($_FILES[$fieldName])) {
            throw new RuntimeException('ファイルフィールドが存在しないため、アップロードに失敗しました');
        }
        $fileData = $_FILES[$fieldName];
        if (!isset($fileData['tmp_name'])) {
            throw new RuntimeException('一時ファイル情報が不足しているため、アップロードに失敗しました');
        }
        if (!isset($fileData['name'])) {
            throw new RuntimeException('ファイル名情報が不足しているため、アップロードに失敗しました');
        }
        if (!isset($fileData['size'])) {
            throw new RuntimeException('ファイルサイズ情報が不足しているため、アップロードに失敗しました');
        }
        if ($fileData['tmp_name'] === '') {
            throw new RuntimeException('一時ファイルのパスが空のため、アップロードに失敗しました');
        }
        if (is_uploaded_file($fileData['tmp_name']) === false) {
            throw new RuntimeException('アップロードされたファイルが確認できないため、アップロードに失敗しました');
        }

        return $this->storeFile(
            $archivesDir,
            $fileData['tmp_name'],
            $fileData['name'],
            $random
        );
    }

    /**
     * ファイルを保存する
     *
     * @param string $archivesDir
     * @param string $path 読み取り元のファイルパス（一時ファイルまたは既存ファイル）
     * @param string $filename 元のファイル名（拡張子を含む）
     * @param bool $random
     * @return array{path: string, type: string, name: string, size: string}
     * @throws RuntimeException
     */
    public function storeFile(
        string $archivesDir,
        string $filepath,
        string $filename,
        bool $random = true
    ): array {
        if (!preg_match('@\.([^.]+)$@', $filename, $match)) {
            throw new RuntimeException('拡張子を取得できなかったため、ファイルの保存に失敗しました: ' . $filename);
        }
        $nameParts = preg_split('/\./', $filename);
        if ($nameParts === false) {
            throw new RuntimeException('ファイル名を分割できなかったため、ファイルの保存に失敗しました: ' . $filename);
        }
        array_pop($nameParts);
        $name = implode('.', $nameParts);

        $extension = $match[1];
        $dir = PublicStorage::archivesDir();
        $mimeType = LocalStorage::getMimeType($filepath);
        if (is_null($mimeType)) {
            throw new RuntimeException('MIMEタイプを取得できなかったため、ファイルの保存に失敗しました: ' . $filename);
        }
        $isPublicStorage = preg_match('/svg/', strtolower($mimeType));

        if ($isPublicStorage) {
            PublicStorage::makeDirectory($archivesDir . $dir);
        } else {
            PrivateStorage::makeDirectory($archivesDir . $dir);
        }
        if (!$random) {
            $path = "{$dir}{$name}.{$extension}";
            if ($isPublicStorage) {
                $path = PublicStorage::uniqueFilePath($path, $archivesDir);
            } else {
                $path = PrivateStorage::uniqueFilePath($path, $archivesDir);
            }
            $name = preg_replace("/(.+)(\.[^.]+$)/", "$1", PublicStorage::mbBasename($path));
        } else {
            $path = $dir . uniqueString() . '.' . $extension;
        }
        $file = $archivesDir . $path;

        $allowedExtensions = array_merge(
            ['svg'],
            configArray('file_extension_document'),
            configArray('file_extension_archive'),
            configArray('file_extension_movie'),
            configArray('file_extension_audio')
        );
        $mimeValidator = new MimeTypeValidator();
        if (!$mimeValidator->validateAllowedByContent($filepath, $allowedExtensions)) {
            throw new RuntimeException('許可されていないファイル形式のため、ファイルの保存に失敗しました: ' . $filename);
        }
        if ($isPublicStorage) {
            // SVGの場合、サニタイズ処理をする
            $dirty = LocalStorage::get($filepath, dirname($filepath));
            $clean = $this->sanitizeSvg($dirty);
            PublicStorage::put($file, $clean);
        } elseif ($content = file_get_contents($filepath)) {
            PrivateStorage::put($file, $content);
        }
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaCreate', $file);
        }
        $fileSize = filesize($filepath);

        return [
            'path' => $path,
            'type' => strtoupper($extension),
            'name' => $name . '.' . $extension,
            'size' => $fileSize !== false ? byteConvert($fileSize) : '0B',
        ];
    }

    /**
     * 画像ファイルを保存する
     *
     * @param string $filepath ソース画像のパス
     * @param string $filename 元のファイル名
     * @param int|null $angle
     * @param bool $original
     * @return array{path: string, type: string, name: string, size: string}
     */
    public function storeImage(
        string $filepath,
        string $filename,
        ?int $angle = null,
        bool $original = true,
    ): array {
        if ($filepath === '') {
            throw new \InvalidArgumentException('ファイルパスが空のため、ファイルの保存に失敗しました');
        }
        if ($filename === '') {
            throw new \InvalidArgumentException('ファイル名が空のため、ファイルの保存に失敗しました');
        }
        $sizes = [
            'normal' => 0,
            'tiny' => 330,
            'square' => -1,
        ];
        if ($original) {
            $sizes['large'] = 99999;
        }
        $isRandomFileName = config('media_image_ramdom_filename', 'off') === 'on';
        $forceLarge = isset($sizes['large']);
        return Image::createImages(
            filepath: $filepath,
            filename: $filename,
            sizes: $sizes,
            destDir: MEDIA_LIBRARY_DIR,
            isRandomFileName: $isRandomFileName,
            angle: $angle,
            forceLarge: $forceLarge
        );
    }

    /**
     * 画像を保存する
     *
     * @param string $filepath
     * @param string $filename
     * @return array{path: string, type: string, name: string, size: string}
     */
    public function storePdfThumbnailImage(
        string $filepath,
        string $filename,
    ): array {
        if ($filepath === '') {
            throw new \InvalidArgumentException('ファイルパスが空のため、ファイルの保存に失敗しました');
        }
        if ($filename === '') {
            throw new \InvalidArgumentException('ファイル名が空のため、ファイルの保存に失敗しました');
        }
        return Image::createImages(
            filepath: $filepath,
            filename: $filename,
            sizes: ['normal' => 99999],
            destDir: MEDIA_LIBRARY_DIR,
            isRandomFileName: true,
            angle: null,
            forceLarge: true
        );
    }

    /**
     * 画像パスから、サイズ違い・拡張子違い（webp）など含めて削除
     *
     * @param string $path この値は、ユーザーの入力値など信頼されない値を指定しない
     * @param bool $removeOriginal
     * @return void
     */
    protected function removeImageFiles(string $path, bool $removeOriginal = true): void
    {
        $edited = MEDIA_LIBRARY_DIR . $path;
        $original = MEDIA_LIBRARY_DIR . otherSizeImagePath($path, 'large');
        PublicStorage::remove($edited);

        if (PublicStorage::exists($edited . '.webp')) {
            PublicStorage::remove($edited . '.webp');
        }
        if ($dirname = dirname($edited)) {
            $dirname .= '/';
        }
        $basename = PublicStorage::mbBasename($edited);
        $fileList = PublicStorage::getFileList($dirname);
        $pattern = '/^.*-' . preg_quote($basename) . '$/';

        foreach ($fileList as $filePath) {
            if (!$removeOriginal && $filePath === $original) {
                continue;
            }
            if (!preg_match($pattern, $filePath)) {
                continue;
            }
            PublicStorage::remove($filePath);
            if (PublicStorage::exists($filePath . '.webp')) {
                PublicStorage::remove($filePath . '.webp');
            }
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('mediaDelete', $filePath);
            }
        }
    }
}
