<?php

use Acms\Services\Facades\PublicStorage;
use Acms\Services\Common\MimeTypeValidator;

/**
 * ファイルアップロードと管理を担当するクラス
 *
 * このクラスは以下の機能を提供します：
 * - ファイルのアップロード処理
 * - アップロードされたファイルの保存
 * - 既存ファイルの削除
 * - ファイル名の一意性確保
 * - 許可された拡張子の検証
 */
class ACMS_POST_File extends ACMS_POST
{
    /**
     * 削除対象のファイルパス
     * @var string|null
     */
    public $delete;

    /**
     * アーカイブディレクトリのパス
     * @var string
     */
    public $ARCHIVES_DIR;

    /**
     * 古いファイルを削除するかどうかのフラグ
     * @var bool
     */
    public $olddel;

    /**
     * 既存のファイルパス
     * @var string
     */
    public $old;

    /**
     * 編集モード（'delete' または ''）
     * @var 'delete'|''
     */
    public $edit;

    /**
     * コンストラクタ
     *
     * @param bool $olddel 古いファイルを削除するかどうか
     */
    public function __construct($olddel = true)
    {
        //-------
        // init
        $this->delete       = null;
        $this->olddel       = $olddel;
        $this->ARCHIVES_DIR = ARCHIVES_DIR;
    }

    /**
     * ファイルの構築と保存を実行
     *
     * @param string $old 既存のファイルパス
     * @param string $filepath アップロードされたファイルパス
     * @param string $name ファイル名
     * @param 'delete'|'' $edit 編集モード
     * @return string 保存されたファイルパス
     */
    public function buildAndSave($old, $filepath, $name, $edit)
    {
        $this->delete = null;
        $this->old = ltrim($old, './');
        $this->edit = $edit;
        $path = '';

        //----------------
        // build and save
        $file = $this->buildFileData($filepath, $name);
        if ($file !== null) {
            // 削除モードの場合は編集や保存は行わない
            $path = $this->editAndSaveFiles($file);
        }
        $this->deleteFile();

        return $path;
    }

    /**
     * アップロードされたファイルデータを構築
     *
     * @param string $filepath アップロードされたファイルパス
     * @param string $name ファイル名
     * @return array{tmp_name: string, name: string}|null ファイルデータの配列
     */
    private function buildFileData($filepath, $name)
    {
        $file = null;

        if ($this->edit === 'delete') {
            // 削除モードの場合は古いファイルを削除する
            $this->delete = $this->ARCHIVES_DIR . $this->old;
            return null;
        }

        if (
            $filepath !== '' &&
            is_uploaded_file($filepath) &&
            preg_match('@^([^/]+)\.([^./]+)$@', $name)
        ) {
            $file = [
                'tmp_name'  => $filepath,
                'name'      => $name,
            ];
            return $file;
        }

        return $file;
    }

    /**
     * ファイルの編集と保存を実行
     *
     * 以下の処理を行います：
     * - ファイルの拡張子チェック
     * - 保存ディレクトリの作成
     * - ファイル名の一意性確保
     * - ファイルの保存
     * - フックの実行
     *
     * @param array{tmp_name: string, name: string} $file 保存するファイルデータの配列
     * @return string 保存されたファイルパス
     */
    private function editAndSaveFiles(array $file)
    {
        $ufile  = $file['tmp_name'];
        $fname  = $file['name'];

        if (!is_uploaded_file($ufile)) {
            // アップロードされていない場合は空文字を返す
            return '';
        }

        if (!preg_match('@^([^/]+)\.([^./]+)$@', $fname, $match)) {
            // ファイル名が不正な場合は空文字を返す
            return '';
        }

        $basename = $match[0];
        $extension = strtolower($match[2]);

        $allowedExtensions = array_merge(
            configArray('file_extension_document'),
            configArray('file_extension_archive'),
            configArray('file_extension_movie'),
            configArray('file_extension_audio')
        );
        $mimeValidator = new MimeTypeValidator();
        if (!$mimeValidator->validateAllowedByContent($ufile, $allowedExtensions)) {
            // 許可されていない拡張子の場合は空文字を返す
            return '';
        }

        $dir = PublicStorage::archivesDir();
        PublicStorage::makeDirectory($this->ARCHIVES_DIR . $dir);

        $path = ('rawfilename' == config('file_savename'))
            ? $dir . $basename : $dir . uniqueString() . '.' . $extension;

        // 重複対応
        $path = PublicStorage::uniqueFilePath($this->ARCHIVES_DIR . $path);
        $path = mb_substr($path, strlen($this->ARCHIVES_DIR));

        if ($content = file_get_contents($ufile)) {
            $content = PublicStorage::put($this->ARCHIVES_DIR . $path, $content);
        }

        Entry::addUploadedFiles($path); // 新規バージョンとして作成する時にファイルをCOPYするかの判定に利用

        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaCreate', $this->ARCHIVES_DIR . $path);
        }

        if (
            $this->delete === '' &&
            $this->old !== '' &&
            $this->old !== $path
        ) {
            // 編集後は古いファイルを削除する
            $this->delete = $this->ARCHIVES_DIR . $this->old;
        }
        return $path;
    }

    /**
     * 不要になったファイルを削除
     *
     * 新規バージョン作成時は削除を行わない
     * @return void
     */
    private function deleteFile()
    {
        if (Entry::isNewVersion()) {
            return;
        }
        if ($this->olddel === true && $this->delete !== null && $this->delete !== '') {
            deleteFile($this->delete, true);
        }
    }
}
