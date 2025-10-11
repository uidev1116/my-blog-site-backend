<?php

namespace Acms\Services\Facades;

/**
 * @method static string|false safeRealpath(string $path) realpath の安全なラッパー関数
 * @method static bool exists(string $path) ファイルが存在するか判定
 * @method static bool isFile(string $path) ファイルか判定
 * @method static bool isDirectory(string $path) ディレクトリか判定
 * @method static bool isExecutable(string $path) 実行可能か判定
 * @method static bool isWritable(string $path) 書き込み可能か判定
 * @method static bool isReadable(string $path) 読み込み可能か判定
 * @method static bool isLink(string $path) シンボリックリンクか判定
 * @method static bool changeMod(string $path, ?int $mode = null) モードを変更
 * @method static bool changeDir(string $path) ディレクトリを変更
 * @method static int getFileSize(string $path) ファイルサイズを取得
 * @method static array{0: int, 1: int, 2: int, 3: string, bits: int, channels: int, mime: string}|false getImageSize(string $path, array &$info = []) 画像サイズを取得
 * @method static string validateDirectoryTraversal(string $baseDir, string $fileName) 指定されたファイル名がディレクトリトラバーサルを含まないか検証し、絶対パスを返します。
 * @method static bool validateDirectoryTraversalPath(string $path, string $publicDir = '', bool $checkExists = true) ディレクトリ・トラバーサル対応のため、パスが公開領域のものか確認する
 * @method static string|null getMimeType(string $path) ファイルのMIMEタイプを取得
 * @method static string|false get(string $path, string $publicDir = '') ファイルの内容を取得
 * @method static resource|false readStream(string $path) ファイルをストリームで読み込む
 * @method static bool remove(string $path) ファイルを削除
 * @method static int put(string $path, string $content) ファイルを書き込む
 * @method static bool copy(string $from, string $to) ファイルをコピー
 * @method static bool move(string $from, string $to) ファイルを移動
 * @method static bool removeDirectory(string $dir) ディレクトリを削除
 * @method static bool copyDirectory(string $from, string $to) ディレクトリをコピー
 * @method static bool makeDirectory(string $path) ディレクトリを作成
 * @method static int lastModified(string $path) ファイルの最終更新日時を取得
 * @method static string[] getFileList(string $path) ディレクトリ内のファイル一覧を取得
 * @method static string archivesDir() アーカイブディレクトリを取得
 * @method static void compress(string $source, string $destination, string $root = '', array $exclude = []) ファイルを圧縮
 * @method static void unzip(string $source, string $destination) ファイルを解凍
 * @method static string uniqueFilePath(string $original, string $prefix = '', int $num = 0) ユニークなファイル名を生成
 * @method static string removeIllegalCharacters(string $source) 不正な文字を削除
 * @method static string mbBasename(string $path, ?string $suffix = null) パスのベース名を取得
 * @method static void setFileMod(int $mod) ファイルのモードを設定
 * @method static void setDirectoryMod(int $mod) ディレクトリのモードを設定
 */
class Storage extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'storage';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
