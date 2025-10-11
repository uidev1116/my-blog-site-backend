<?php

namespace Acms\Services\Facades;

/**
 * @method static bool validate(?int $bid = null) 指定したブログでメディアライブラリが有効かどうかを確認
 * @method static bool canEdit(int $mid) 指定したメディアを編集できるかどうかを確認
 * @method static false|array{tags: string[], name: string, file: array{name: string, tmp_name: string, type: string, size: int}, size: int, type: string, extension: string} getBaseInfo(array{name: string, tmp_name: string, type: string, size: int} $fileObj, string $tags, string $name) メディアの基本情報を取得
 * @method static array{path: string, name: string, original: string} copyImages(int $mid, string $filename = '') メディアの画像をコピー
 * @method static array{path: string, name: string} copyFiles(int $mid, string $filename = '') メディアのファイルをコピー
 * @method static array{path: string, type: string, name: string, size: string, filesize: int, extension: string} uploadImage(string $fieldName = 'file', bool $original = true) 画像をアップロード
 * @method static array{path: string, type: string, name: string, size: string} uploadPdfThumbnail(string $name) PDFのサムネイルをアップロード
 * @method static array{path: string, type: string, name: string, size: string, filesize: int, extension: string} uploadSvg(string $size, string $fieldName = 'file') SVGをアップロード
 * @method static array{path: string, type: string, name: string, size: string, filesize: int, extension: string}|false uploadFile(string $size, string $fieldName = 'file') ファイルをアップロード
 * @method static void deleteImage(int $mid, bool $removeOriginal = true) 画像ファイルを削除
 * @method static void deleteThumbnail(int $mid) サムネイル画像を削除
 * @method static void deleteFile(int $mid) ファイルを削除
 * @method static array rename(array $data, string $rename) メディアの名前を変更
 * @method static string urlencode(string $path) パスをURLエンコード
 * @method static string cacheBusting(string $updated) キャッシュバスティングを適用
 * @method static bool isImageFile(string $type) 指定したファイルが画像かどうかを確認
 * @method static bool isSvgFile(string $type) 指定したファイルがSVGかどうかを確認
 * @method static bool isFile(string $type) 指定したファイルがファイルかどうかを確認
 * @method static string getEdited(string $path)
 * @method static string getImageThumbnail(string $path) 画像のサムネイルを取得
 * @method static string getSvgThumbnail(string $path) SVGのサムネイルパスを取得
 * @method static string getFileThumbnail(string $extension) ファイルのサムネイルパスを取得
 * @method static string getPdfThumbnail(string $path) PDFのサムネイルパスを取得
 * @method static string getImagePermalink(string $path) 画像のパーマリンクを取得
 * @method static string getFilePermalink(int $mid, bool $fullpath = true) ファイルのパーマリンクを取得
 * @method static string getDownloadLinkHash(int $mid) ダウンロードリンクのハッシュを取得
 * @method static string getFileOldPermalink(string $path, bool $fullpath = true) ファイルの古いパーマリンクを取得
 * @method static string getOriginal(string $original) オリジナル画像のパスを取得
 * @method static false|void filterTag(\SQL_Select $SQL, string[] $tags) タグをフィルタリング
 * @method static void saveTags(int $mid, string $tags, ?int $bid = null) タグを保存
 * @method static void deleteTag(string $tagName, ?int $bid = null) タグを削除
 * @method static void updateTag(string $oldTag, string $newTag, ?int $bid = null) タグを更新
 * @method static void insertMedia(int $mid, array $data) メディアを挿入
 * @method static void updateMedia(int $mid, array $data) メディアを更新
 * @method static array{media_status: string, media_title: string, media_label: string, media_last_modified: string, media_datetime: string, media_id: int, media_bid: int, media_blog_name: string, media_user_id: int, media_user_name: string, media_last_update_user_id: int|'', media_last_update_user_name: string, media_size: string, media_filesize: int, media_path: string, media_edited: string, media_original: string, media_thumbnail: string, media_permalink: string, media_type: string, media_ext: string, media_caption: string, media_link: string, media_alt: string, media_text: string, media_focal_point: string, media_editable: bool, media_pdf_page: string, checked: false} buildJson(int $mid, array $data, string $tags, int $bid) JSONをビルド
 * @method static string[] getMediaArchiveList(\SQL $sql) メディアのアーカイブリストを取得
 * @method static string[] getMediaTagList(\SQL $sql) メディアのタグリストを取得
 * @method static string[] getMediaExtensionList(\SQL $sql) メディアの拡張子リストを取得
 * @method static string getMediaLabel(int $mid) メディアのラベルを取得
 * @method static array<int, array<string, mixed>> mediaEagerLoad(int[] $midiaIds) メディアIDからメディア情報を取得
 * @method static array<int, array<string, mixed>> mediaEagerLoadFromUnit(\Acms\Services\Unit\UnitCollection $collection) ユニットモデル一覧からメディア情報を取得
 * @method static array{ path: string, width: string, height: string, permalink: string, icon: string, iconWidth: string, iconHeight: string}[] getMediaList(int[] $midiaIds) メディアIDから整形されたメディア一覧を取得
 * @method static array{mid: int, bid: int, status: string, path: string, thumbnail: string, name: string, size: string, filesize: int, type: string, extension: string, original: string, update_date: string, upload_date: string, field1: string, field2: string, field3: string, field4: string, field5: string, field6: string, blog_name: string, user_id: int, user_name: string, last_update_user_id: int, last_update_user_name: string, editable: bool}|array{} getMedia(int $mid) メディアを取得
 * @method static void deleteItem(int $mid) メディアを削除
 * @method static void injectMediaField(\Field $Field, array $mediaList, string[] $useMediaField) \Fieldにメディアデータを注入
 * @method static never|void downloadFile(int $mid) ファイルをダウンロード
 * @method static string sanitizeSvg(string $input) SVGをサニタイズ
 */
class Media extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'media';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
