<?php

use Acms\Services\Facades\Image;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Logger as AcmsLogger;

/**
 * 画像アップロードと管理を担当するクラス
 *
 * このクラスは以下の機能を提供します：
 * - 画像のアップロード処理
 * - 画像のリサイズ（tiny, normal, large, squareサイズ）
 * - 画像の回転
 * - 画像の削除
 * - Base64エンコードされた画像の処理
 * - WebP形式への変換
 *
 * @phpstan-type EditOption array{
 *  size: int,
 *  angle?: int,
 * }
 *
 * @phpstan-type EditOptions array{
 *  tiny?: EditOption,
 *  square?: EditOption,
 *  normal?: EditOption,
 *  large?: EditOption,
 * }
 */
class ACMS_POST_Image extends ACMS_POST
{
    private const ARCHIVES_TMP_DIR = ARCHIVES_DIR . 'tmp/';

    /**
     * 既存の画像パス
     * @var string
     */
    public $old;

    /**
     * 保存先のパス
     * @var string
     */
    public $path;

    /**
     * リサイズ後のサイズ
     * @var int
     */
    public $size;

    /**
     * 選択されたサイズ
     * @var string|int
     */
    public $selectSize;

    /**
     * 既存の画像サイズ
     * @var string|int
     */
    public $oldSize;

    /**
     * 編集モード（'none', 'deleteLarge', 'rotate270', 'rotate90', 'rotate180', 'delete'）
     * @var string
     */
    public $edit;

    /**
     * 削除対象の画像パス
     * @var string|null
     */
    public $delete;

    /**
     * 回転角度
     * @var int|null
     */
    public $angle;

    /**
     * アーカイブディレクトリのパス
     * @var string
     */
    public $ARCHIVES_DIR;

    /**
     * 古い画像を削除するかどうかのフラグ
     * @var bool
     */
    public $olddel;

    /**
     * サムネイルサイズ
     * @var int
     */
    public $tinySize;

    /**
     * 大サイズ
     * @var int
     */
    public $largeSize;

    /**
     * 正方形サイズ
     * @var int
     */
    public $squareSize;

    /**
     * 標準サイズの基準（width/height）
     * @var string|null
     */
    public $stdSide;

    /**
     * サムネイルサイズの基準（width/height）
     * @var string|null
     */
    public $stdSideTiny;

    /**
     * 大サイズの基準（width/height）
     * @var string|null
     */
    public $stdSideLarge;

    /**
     * コンストラクタ
     *
     * @param bool $olddel 古い画像を削除するかどうか
     */
    public function __construct($olddel = true)
    {
        //-------
        // init
        $this->delete = '';
        $this->angle = null;

        $this->olddel = $olddel;
        $this->ARCHIVES_DIR = ARCHIVES_DIR;
    }

    /**
     * Base64エンコードされた文字列からデータ部分を抽出
     *
     * @param string $string Base64エンコードされた文字列
     * @return string Base64データ部分
     */
    public static function getBase64Data($string)
    {
        $temp = explode(',', $string);
        if (count($temp) > 1) {
            $data = $temp[1];
        } else {
            $data = $temp[0];
        }
        return $data;
    }

    /**
     * Base64データを画像ファイルに変換
     *
     * @param string|array $base64 Base64エンコードされた画像データ
     * @param string $id ファイルID
     * @param int|false $index 配列インデックス（複数ファイルの場合）
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public static function base64DataToImage($base64, $id, $index = false)
    {
        if (is_string($base64) && $base64 === '') {
            return false;
        }
        if (is_array($base64) && count($base64) === 0) {
            return false;
        }

        // base64データを保存するディレクトリはローカルストレージに保存する
        // 一時ファイルとして保存するだけなので、外部のストレージには保存しない
        if (!LocalStorage::exists(self::ARCHIVES_TMP_DIR)) {
            if (!LocalStorage::makeDirectory(self::ARCHIVES_TMP_DIR)) {
                return false;
            }
        }

        $name       = $_FILES[$id]['name'] ?? '';
        $type       = $_FILES[$id]['type'] ?? '';
        $tmp_name   = $_FILES[$id]['tmp_name'] ?? '';
        $error      = $_FILES[$id]['error'] ?? 0;
        $size       = $_FILES[$id]['size'] ?? 0;

        //----------------
        // 複数アップロード
        if (is_array($base64)) {
            foreach ($base64 as $i => $row) {
                $row = self::getBase64Data($row);
                if ($row === '') {
                    continue;
                }
                try {
                    $tmpFile    = uniqueString() . '.jpeg';
                    $data       = base64_decode($row, true);
                    if ($data === false) {
                        throw new \RuntimeException('Invalid base64 data');
                    }
                    $dest       = self::ARCHIVES_TMP_DIR . $tmpFile;
                    LocalStorage::put($dest, $data);
                    $tmpPath = LocalStorage::safeRealpath($dest);

                    $name[$i]     = $tmpFile;
                    $type[$i]     = 'image/jpeg';
                    $tmp_name[$i] = $tmpPath;
                    $error[$i]    = '';
                    $size[$i]     = LocalStorage::getFileSize($dest);
                } catch (\Exception $e) {
                    AcmsLogger::notice($e->getMessage(), Common::exceptionArray($e));
                    continue;
                }
            }

            //----------------
            // 単数アップロード
        } else {
            try {
                $tmpFile    = uniqueString() . '.jpeg';
                $base64     = self::getBase64Data($base64);
                $data       = base64_decode($base64, true);
                if ($data === false) {
                    throw new \RuntimeException('Invalid base64 data');
                }
                $dest       = self::ARCHIVES_TMP_DIR . $tmpFile;

                LocalStorage::put($dest, $data);
                $tmpPath = LocalStorage::safeRealpath($dest);

                // 多言語対応
                if (is_int($index)) {
                    $name[$index]       = $tmpFile;
                    $type[$index]       = 'image/jpeg';
                    $tmp_name[$index]   = $tmpPath;
                    $error[$index]      = '';
                    $size[$index]       = LocalStorage::getFileSize($dest);
                } else {
                    $name       = $tmpFile;
                    $type       = 'image/jpeg';
                    $tmp_name   = $tmpPath;
                    $error      = '';
                    $size       = LocalStorage::getFileSize($dest);
                }
            } catch (\Exception $e) {
                AcmsLogger::notice($e->getMessage(), Common::exceptionArray($e));
            }
        }

        if (is_array($name) && count($name) === 0) {
            return false;
        }

        if (is_string($name) && $name === '') {
            return false;
        }

        $_FILES[$id] = [
            'name'      => $name,
            'type'      => $type,
            'tmp_name'  => $tmp_name,
            'error'     => $error,
            'size'      => $size,
        ];

        return true;
    }

    /**
     * 画像の構築と保存を実行
     *
     * @param string $old 保存されている画像パス
     * @param string $filepath アップロードされたファイルパス
     * @param string $size ユニットで選択された画像サイズ ex: width820:acms-col-sm-12
     * @param 'none'|'deleteLarge'|'rotate270'|'rotate90'|'rotate180'|'delete' $edit 編集モード
     * @param string $size_old 現在保存されている画像のサイズ ex: with820:acms-col-sm-12, w820
     * @return array{edit: EditOptions, target: string, file: string, path: string}|null 保存された画像情報
     */
    public function buildAndSave($old, $filepath, $size, $edit, $size_old = '')
    {
        $this->old = $old;
        $this->selectSize = $size;
        $this->oldSize = $size_old;
        $this->edit = $edit;
        $this->delete = null;

        $this->old = ltrim($old, './');
        $this->path = $this->old;

        if ($this->edit === 'delete') {
            if ($this->old !== '') {
                $this->delete = $this->ARCHIVES_DIR . $this->old;
            }
            $this->old = '';
        } elseif ($this->edit === 'deleteLarge') {
            if ($this->old !== '') {
                $file = $this->ARCHIVES_DIR . $this->old;
                if (PublicStorage::isFile($file)) {
                    $name = PublicStorage::mbBasename($file);
                    $dir = substr($file, 0, (strlen($file) - strlen($name)));
                    PublicStorage::remove($dir . 'large-' . $name);
                    if (HOOK_ENABLE) {
                        $Hook = ACMS_Hook::singleton();
                        $Hook->call('mediaDelete', $dir . 'large-' . $name);
                    }
                }
            }
        } elseif (substr($edit, 0, 6) === 'rotate') {
            $this->angle = intval(substr($edit, 6));
        }

        //----------------
        // build and save

        $this->buildSize($size);
        $insertData = $this->buildInsertData($filepath);
        $updateData = $this->buildUpdateData();
        $data = $insertData ?? $updateData;

        if ($data !== null) {
            $this->editAndSaveImage($data);
        }
        $this->deleteImage();
        PublicStorage::removeDirectory(self::ARCHIVES_TMP_DIR);

        return $data;
    }

    /**
     * 新規アップロード画像のデータを構築
     *
     * @param string $filepath アップロードされたファイルパス
     * @return array{edit: EditOptions, target: string, file: string, path: string}|null 画像データ
     */
    private function buildInsertData(string $filepath)
    {
        $result = null;

        do {
            if ($this->delete !== null && $this->delete !== '') {
                // 削除対象の画像がある場合は処理を中断
                break;
            }

            if ($filepath === '') {
                // ファイル名が空の場合は処理を中断
                break;
            }

            if (!$xy = LocalStorage::getImageSize($filepath)) {
                // ローカルストレージに存在しない場合は処理を中断
                break;
            }

            if (!$this->isUploadedFile($filepath)) {
                // アップロードされていない場合
                break;
            }

            $Edit = [];

            if ($this->old !== '') {
                $this->delete = $this->ARCHIVES_DIR . $this->old;
                $this->old = '';
            }

            $longSide = max($xy[0], $xy[1]);
            $mime = $xy['mime'];

            $Edit['tiny']['size'] = $this->tinySize;
            if ($this->squareSize > 0) {
                $Edit['square']['size'] = $this->squareSize;
            }
            $Edit['normal']['size'] = $this->size;

            if ($this->angle !== null) {
                $Edit['tiny']['angle'] = $this->angle;
                if ($this->squareSize > 0) {
                    $Edit['square']['angle'] = $this->angle;
                }
                $Edit['normal']['angle'] = $this->angle;
            }

            if ($this->size !== null && $longSide > $this->size && $this->edit !== 'deleteLarge') {
                $Edit['large']['size'] = ($longSide > $this->largeSize) ? $this->largeSize : $longSide;
                if ($this->angle !== null) {
                    $Edit['large']['angle'] = $this->angle;
                }
            }

            $target = $filepath;
            $dir = PublicStorage::archivesDir();
            PublicStorage::makeDirectory($this->ARCHIVES_DIR . $dir);

            $ext = $mime ? Image::detectImageExtenstion($mime) : 'jpg';
            $path = $dir . uniqueString(8) . '.' . $ext;
            $file = $this->ARCHIVES_DIR . $path;

            Entry::addUploadedFiles($path); // 新規バージョンとして作成する時にファイルをCOPYするかの判定に利用

            $result = [
                'edit' => $Edit,
                'target' => $target,
                'file' => $file,
                'path' => $path,
            ];
        } while (false);

        return $result;
    }

    /**
     * 既存画像の更新データを構築
     *
     * @return array{edit: EditOptions, target: string, file: string, path: string}|null 画像データ
     */
    private function buildUpdateData()
    {
        $result = null;

        if ($this->old !== '' && ($xy = PublicStorage::getImageSize($this->ARCHIVES_DIR . $this->old))) {
            $Edit = [];
            $longSide   = max($xy[0], $xy[1]);

            if ($this->size !== null) {
                $Edit['normal']['size'] = $this->size;
            } elseif ($this->angle !== null) {
                $Edit['normal']['size'] = $longSide;
            }

            $Edit['tiny']['size'] = $this->tinySize;

            if ($this->angle !== null) {
                $Edit['tiny']['angle'] = $this->angle;

                if ($this->squareSize > 0) {
                    $Edit['square']['size']     = $this->squareSize;
                    $Edit['square']['angle']    = $this->angle;
                }
                $Edit['normal']['angle'] = $this->angle;
            }

            $path   = $this->old;
            $file   = $this->ARCHIVES_DIR . $this->old;
            $target = $file;
            $large  = preg_replace('@(.*/)([^/]*)$@', '$1large-$2', $this->old);

            if (PublicStorage::getImageSize($this->ARCHIVES_DIR . $large)) {
                if ($this->angle !== null) {
                    $Edit['large']['size']  = $this->largeSize;
                    $Edit['large']['angle'] = $this->angle;
                    if ($this->size === null) {
                        $xy = PublicStorage::getImageSize($file);
                        $Edit['normal']['size'] = max($xy[0], $xy[1]);
                    }
                }
                $target = $this->ARCHIVES_DIR . $large;
            }

            if ($this->angle !== null) {
                $this->deleteExtensionImage($this->ARCHIVES_DIR . $this->old);
            }

            if ($this->edit === 'none' && $this->selectSize === $this->oldSize) {
                $file = '';
            }
            $result = [
                'edit'      => $Edit,
                'target'    => $target,
                'file'      => $file,
                'path'      => $path,
            ];
        }

        return $result;
    }

    /**
     * 画像の編集と保存を実行
     *
     * 以下の処理を行います：
     * - 画像のリサイズ（tiny, normal, large, squareサイズ）
     * - 画像の回転
     * - WebP形式への変換
     *
     * @param array{edit: EditOptions, target: string, file: string, path: string} $data 編集する画像データ
     * @return void
     */
    private function editAndSaveImage(array $data): void
    {
        if ($data['target'] === '') {
            return;
        }

        if ($data['file'] === '') {
            return;
        }

        foreach (['tiny', 'square', 'normal', 'large'] as $type_) {
            if (!isset($data['edit'][$type_])) {
                continue;
            }
            $label = $type_;
            $to = $data['edit'][$type_];

            $pfx    = ('normal' == $label) ? '' : $label . '-';
            $_file  = preg_replace('@(.*/)([^/]*)$@', '$1' . $pfx . '$2', $data['file']);
            if ($_file === null) {
                continue;
            }
            if (!preg_match('@\.([^.]+)$@', $_file, $match)) {
                continue;
            }
            $ext = $match[1];

            $_size  = $to['size'];
            $_angle = $to['angle'] ?? null;

            ///* [CMS-762] (2).引き継いできたsizeを、指定があれば特定の辺に適用
            $_width = null;
            $_height = null;

            // width
            if (
                ($label === 'normal' && ($this->stdSide === 'w' || $this->stdSide === 'width')) ||
                ($label === 'tiny' && ($this->stdSideTiny === 'w' || $this->stdSideTiny === 'width')) ||
                ($label === 'large' && ($this->stdSideLarge === 'w' || $this->stdSideLarge === 'width'))
            ) {
                $_width = $_size;
                $_size  = null;
            }
            // height
            if (
                ($label === 'normal' && ($this->stdSide === 'h' || $this->stdSide === 'height')) ||
                ($label === 'tiny' && ($this->stdSideTiny === 'h' || $this->stdSideTiny === 'height')) ||
                ($label === 'large' && ($this->stdSideLarge === 'h' || $this->stdSideLarge === 'height'))
            ) {
                $_height = $_size;
                $_size   = null;
            }
            // square
            if ($label === 'square') {
                $_width  = $_size;
                $_height = $_size;
            }

            try {
                Image::resizeImg($data['target'], $_file, $ext, $_width, $_height, $_size, $_angle);
                if (HOOK_ENABLE) {
                    $Hook = ACMS_Hook::singleton();
                    $Hook->call('mediaCreate', $_file);
                }
            } catch (\Exception $e) {
                AcmsLogger::error('GDによる画像の生成に失敗しました', Common::exceptionArray($e, ['path' => $_file]));
            }
        }
    }

    /**
     * 不要になった画像を削除
     *
     * 新規バージョン作成時は削除を行わない
     * @return void
     */
    private function deleteImage(): void
    {
        if (Entry::isNewVersion()) {
            return;
        }
        if ($this->delete !== null && $this->delete !== '') {
            if (PublicStorage::isFile($this->delete)) {
                $name   = PublicStorage::mbBasename($this->delete);
                $dir    = substr($this->delete, 0, (strlen($this->delete) - strlen($name)));
                if ($this->olddel === true) {
                    $this->deleteExtensionImage($this->delete);

                    PublicStorage::remove($this->delete);
                    PublicStorage::remove($dir . 'tiny-' . $name);
                    PublicStorage::remove($dir . 'large-' . $name);
                    PublicStorage::remove($dir . 'square-' . $name);

                    PublicStorage::remove($this->delete . '.webp');
                    PublicStorage::remove($dir . 'tiny-' . $name . '.webp');
                    PublicStorage::remove($dir . 'large-' . $name . '.webp');
                    PublicStorage::remove($dir . 'square-' . $name . '.webp');

                    if (HOOK_ENABLE) {
                        $Hook = ACMS_Hook::singleton();
                        $Hook->call('mediaDelete', $this->delete);
                        $Hook->call('mediaDelete', $dir . 'tiny-' . $name);
                        $Hook->call('mediaDelete', $dir . 'large-' . $name);
                        $Hook->call('mediaDelete', $dir . 'square-' . $name);

                        $Hook->call('mediaDelete', $this->delete . '.webp');
                        $Hook->call('mediaDelete', $dir . 'tiny-' . $name . '.webp');
                        $Hook->call('mediaDelete', $dir . 'large-' . $name . '.webp');
                        $Hook->call('mediaDelete', $dir . 'square-' . $name . '.webp');
                    }
                }
            }
        }
    }

    /**
     * 拡張子付きの画像を削除
     *
     * @param string $path 削除対象の画像パス
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    private function deleteExtensionImage($path)
    {
        if (!PublicStorage::isFile($path)) {
            return false;
        }

        $name = PublicStorage::mbBasename($path);
        $dir = substr($path, 0, (strlen($path) - strlen($name)));

        $fileList = PublicStorage::getFileList($dir);
        $pattern = '/^.*-' . preg_quote($name) . '$/';

        foreach ($fileList as $filePath) {
            if (!preg_match($pattern, $filePath)) {
                continue;
            }
            if (preg_match('/(tiny|large|square)-(.*)$/', $filePath)) {
                continue;
            }
            PublicStorage::remove($filePath);

            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('mediaDelete', $filePath);
            }
        }

        return true;
    }

    /**
     * 画像サイズの情報を設定
     *
     * 以下のサイズを設定します：
     * - 標準サイズ（normal）
     * - サムネイルサイズ（tiny）
     * - 大サイズ（large）
     * - 正方形サイズ（square）
     *
     * @param string $size 画像サイズ ex: width820:acms-col-sm-12
     */
    private function buildSize($size)
    {
        $tinySize     = config('image_size_tiny');
        $largeSize    = config('image_size_large');
        $squareSize   = intval(config('image_size_square'));

        // normal
        if (preg_match('/^(w|width|h|height)(\d+)/', $size, $matches)) {
            $this->stdSide  = strval($matches[1]);
            $this->size     = intval($matches[2]);
        } else {
            $this->size     = intval($size);
        }
        // tiny
        if (preg_match('/^(w|width|h|height)(\d+)/', $tinySize, $matches)) {
            $this->stdSideTiny  = strval($matches[1]);
            $this->tinySize     = intval($matches[2]);
        } else {
            $this->tinySize     = intval($tinySize);
        }
        // large
        if (preg_match('/^(w|width|h|height)(\d+)/', $largeSize, $matches)) {
            $this->stdSideLarge   = strval($matches[1]);
            $this->largeSize      = intval($matches[2]);
        } else {
            $this->largeSize      = intval($largeSize);
        }
        // square
        if ($squareSize < 1) {
            $this->squareSize = -1;
        } else {
            $this->squareSize = $squareSize;
        }

        if ($this->size !== 0 and $this->size < $this->tinySize) {
            $this->tinySize = $this->size;
        }
    }

    /**
     * アップロードされたファイルかどうかを判定
     *
     * @param string $path ファイルパス
     * @return bool
     */
    private function isUploadedFile(string $path): bool
    {
        if (is_uploaded_file($path)) {
            return true;
        }
        $files = array_map(function ($file) {
            return LocalStorage::mbBasename($file);
        }, LocalStorage::getFileList(self::ARCHIVES_TMP_DIR));
        $filename = LocalStorage::mbBasename($path);
        if (in_array($filename, $files, true)) {
            // ARCHIVES_TMP_DIR にあるファイルはアップロードされたファイルとして扱う
            return true;
        }
        return false;
    }
}
