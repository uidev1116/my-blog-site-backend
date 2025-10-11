<?php

namespace Acms\Services\Storage\Contracts;

interface Filesystem
{
    /**
     * realpath の安全なラッパー関数
     *
     * @param string $path
     * @return string|false
     */
    public function safeRealpath(string $path);

    /**
     * ファイルの存在確認
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * 指定したパスがファイルかどうかを判定
     *
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool;

    /**
     * 指定したパスがディレクトリかどうかを判定
     *
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool;

    /**
     * 実行可能ファイルかどうか
     *
     * @param string $path
     * @return bool
     */
    public function isExecutable(string $path): bool;

    /**
     * 書き込み可能かどうか
     *
     * @param string $path
     * @return bool
     */
    public function isWritable(string $path): bool;

    /**
     * 読み込み可能かどうか
     *
     * @param string $path
     * @return bool
     */
    public function isReadable(string $path): bool;

    /**
     * ファイルがシンボリックリンクかどうか
     *
     * @param string $path
     * @return bool
     */
    public function isLink(string $path): bool;

    /**
     * ファイルのパーミッションを変更する
     *
     * @param string $path
     * @param int|null $mode
     * @return bool
     */
    public function changeMod(string $path, ?int $mode = null): bool;

    /**
     * カレントディレクトリを変更する
     *
     * @param string $path
     * @return bool
     */
    public function changeDir(string $path): bool;

    /**
     * ファイルサイズを取得する
     *
     * @param string $path
     * @return int
     */
    public function getFileSize(string $path): int;

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
    public function getImageSize(string $path, array &$info = []);

    /**
     * ファイルのMIMEタイプを取得
     *
     * @param string $path
     * @return string|null
     */
    public function getMimeType(string $path): ?string;

    /**
     * 指定されたファイル名がディレクトリトラバーサルを含まないか検証し、絶対パスを返します。
     *
     * @param string $baseDir
     * @param string $fileName
     * @return string
     */
    public function validateDirectoryTraversal(string $baseDir, string $fileName): string;

    /**
     * ディレクトリ・トラバーサル対応のため、パスが公開領域のものか確認する
     *
     * @param string $path
     * @param string $publicDir
     * @return boolean
     */
    public function validateDirectoryTraversalPath($path, $publicDir = '');

    /**
     * ファイルを取得する
     *
     * @param string $path 取得したいファイルパス
     * @param string $publicDir 設定されたディレクトリ以下に取得できるファイルを制限（index.phpからの相対パス可）
     * @return string|false
     * @throws \RuntimeException
     */
    public function get(string $path, string $publicDir = '');

    /**
     * ファイルをストリームで読み込む
     *
     * @param string $path
     * @return resource|false
     */
    public function readStream(string $path);

    /**
     * ファイルを削除する
     *
     * @param string $path
     * @return bool
     */
    public function remove(string $path): bool;

    /**
     * ファイルを保存する
     *
     * @param string $path
     * @param string $content
     * @return int
     */
    public function put(string $path, string $content): int;

    /**
     * ファイルをコピーする
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy(string $from, string $to): bool;

    /**
     * ファイルを移動する
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move(string $from, string $to): bool;

    /**
     * ディレクトリを削除する
     *
     * @param string $dir
     * @return bool
     */
    public function removeDirectory(string $dir): bool;

    /**
     * ディレクトリをコピーする
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copyDirectory(string $from, string $to): bool;

    /**
     * ディレクトリを作成する
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory(string $path): bool;

    /**
     * ファイルの更新日時を取得する
     *
     * @param string $path
     * @return int Unix time stamp
     */
    public function lastModified(string $path): int;

    /**
     * ディレクトリ内のファイル一覧を取得する
     *
     * @param string $path
     * @return string[]
     */
    public function getFileList(string $path): array;

    /**
     * ブログ・年月を考慮したパスを取得
     *
     * @return string
     */
    public function archivesDir(): string;

    /**
     * 圧縮する
     *
     * @param string $source
     * @param string $destination
     * @param string $root
     * @param array $exclude
     * @return void
     */
    public function compress(string $source, string $destination, string $root = '', array $exclude = []): void;

    /**
     * 解凍する
     *
     * @param string $source
     * @param string $destination
     * @return void
     */
    public function unzip(string $source, string $destination): void;

    /**
     * ユニークなファイルパスを取得
     *
     * @param string $original
     * @param int $num
     * @return string
     */
    public function uniqueFilePath(string $original, string $prefix = '', int $num = 0): string;

    /**
     * ファイル名から不正な文字を削除
     *
     * @param string $source
     * @return string
     */
    public function removeIllegalCharacters(string $source): string;
}
