<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Services\Unit\Contracts\ExportEntry;
use Acms\Services\Unit\Contracts\ImageUnit;
use Acms\Services\Unit\Contracts\EagerLoadingMedia;
use Acms\Services\Unit\Contracts\UnitListModule;
use Acms\Services\Facades\Media as MediaHelper;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Common;
use Acms\Traits\Unit\SizeableUnitTrait;
use Acms\Services\Unit\Contracts\SizeableUnitInterface;
use Template;
use DOMDocument;
use SQL;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\UnitMultiLangTrait;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Media extends Model implements AlignableUnitInterface, ImageUnit, UnitListModule, ExportEntry, EagerLoadingMedia, AnkerUnitInterface, SizeableUnitInterface
{
    use AlignableUnitTrait;
    use SizeableUnitTrait;
    use AnkerUnitTrait;
    use UnitMultiLangTrait;

    /**
     * ユニットの独自データ
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * メイン画像ユニットかどうか
     * @var bool
     */
    private $isPrimaryImage = false;

    /**
     * Eager Load されたメディアデータ
     * @var array<int, array<string, mixed>>
     */
    private $eagerLoadedMedia = [];

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'media_id' => $this->getMediaIds()[0] ?? '',
            'media_enlarged' => $this->getField4(),
            'media_size' => $this->getSize(),
            ...$this->attributes,
        ];
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * メディアIDを取得
     * @return int[]
     */
    public function getMediaIds(): array
    {
        return array_map('intval', $this->explodeUnitDataTrait($this->getField1()));
    }

    /**
     * 事前読み込みメディアを取得
     *
     * @inheritDoc
     */
    public function getEagerLoadedMedia(): array
    {
        return $this->eagerLoadedMedia;
    }

    /**
     * 事前読み込みメディアを設定
     *
     * @inheritDoc
     */
    public function setEagerLoadedMedia(array $media): void
    {
        $this->eagerLoadedMedia = $media;
    }

    /**
     * メイン画像のパスを取得。メディアの場合メディアIDを取得
     *
     * @inheritDoc
     */
    public function getPaths(): array
    {
        /** @var int[] $mediaIds */
        $mediaIds = array_map('intval', $this->explodeUnitDataTrait($this->getField1()));
        return array_map(function (int $mediaId) {
            $media = isset($this->eagerLoadedMedia[$mediaId]) ? $this->eagerLoadedMedia[$mediaId] : null;
            if (is_null($media)) {
                return '';
            }
            return $media['media_path'];
        }, $mediaIds);
    }

    /**
     * メイン画像のAltを取得
     *
     * @inheritDoc
     */
    public function getAlts(): array
    {
        return $this->explodeUnitDataTrait($this->getField3());
    }

    /**
     * メイン画像のキャプションを取得
     *
     * @inheritDoc
     */
    public function getCaptions(): array
    {
        return $this->explodeUnitDataTrait($this->getField2());
    }

    /**
     * @inheritDoc
     */
    public function isPrimaryImage(): bool
    {
        return $this->isPrimaryImage;
    }

    /**
     * @inheritDoc
     */
    public function setIsPrimaryImage(bool $isPrimaryImage): void
    {
        $this->isPrimaryImage = $isPrimaryImage;
    }

    /**
     * @inheritDoc
     */
    public function canBePrimaryImage(): bool
    {
        $mediaIds = $this->getMediaIds();
        if (count($mediaIds) === 0) {
            return false;
        }

        static $cache = [];
        $cacheKey = md5(implode(',', $mediaIds));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $data = MediaHelper::mediaEagerLoad($mediaIds);
        $medias = array_values($data);

        // すべてのメディアが画像である必要がある
        $canBePrimaryImage = !array_find($medias, function ($media) {
            return !in_array($media['media_type'], ['image'], true);
        });
        $cache[$cacheKey] = $canBePrimaryImage;
        return $canBePrimaryImage;
    }

    /**
     * エントリーのエクスポートでエクスポートするアセットを返却
     *
     * @return string[]
     */
    public function exportArchivesFiles(): array
    {
        return [];
    }

    /**
     * エントリーのエクスポートでエクスポートするメディアIDを返却
     *
     * @return int[]
     */
    public function exportMediaIds(): array
    {
        return array_map('intval', $this->explodeUnitDataTrait($this->getField1()));
    }

    /**
     * エントリーのエクスポートでエクスポートするモジュールIDを返却
     *
     * @inheritDoc
     */
    public function exportModuleId(): ?int
    {
        return null;
    }

    /**
     * Unit_Listモジュールを描画
     *
     * @param Template $tpl
     * @return array
     */
    public function renderUnitListModule(Template $tpl): array
    {
        $data = $this->explodeUnitDataTrait($this->getField1());
        $mediaId = $data[0] ?? $data;
        if (empty($mediaId)) {
            return [];
        }
        $vars = [];
        $eagerLoadedMedia = $this->getEagerLoadedMedia();
        if (isset($eagerLoadedMedia[$mediaId])) {
            $media = $eagerLoadedMedia[$mediaId];
            $mediaType = $media['media_type'];
            $cacheBusting = MediaHelper::cacheBusting($media['media_update_date']);
            if ($mediaType === 'image') {
                $vars['normal'] = Common::resolveUrl($media['media_path'], MEDIA_LIBRARY_DIR) . $cacheBusting;
                $vars['large'] = Common::resolveUrl($media['media_original'], MEDIA_LIBRARY_DIR) . $cacheBusting;
            } elseif ($mediaType === 'file') {
                if (empty($media['media_status'])) {
                    $vars['download'] = MediaHelper::getFileOldPermalink($media['media_path'], false);
                } else {
                    $vars['download'] = MediaHelper::getFilePermalink($media['media_id'], false);
                }
            }
        }
        return $vars;
    }

    /**
     * ユニットタイプを取得
     *
     * @inheritDoc
     */
    public static function getUnitType(): string
    {
        return 'media';
    }

    /**
     * ユニットラベルを取得
     *
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('メディア');
    }

    /**
     * ユニットのデフォルト値をセット
     *
     * @param string $configKeyPrefix
     * @param int $configIndex
     * @return void
     */
    public function setDefault(string $configKeyPrefix, int $configIndex): void
    {
        $this->setField1(config("{$configKeyPrefix}field_1", '', $configIndex));
        $this->setField2(config("{$configKeyPrefix}field_2", '', $configIndex));
        $this->setField3(config("{$configKeyPrefix}field_3", '', $configIndex));
        $this->setField4(config("{$configKeyPrefix}field_4", '', $configIndex));
        $this->setField5(config("{$configKeyPrefix}field_5", '', $configIndex));
        $this->setField7(config("{$configKeyPrefix}field_7", '', $configIndex));
    }

    /**
     * @inheritDoc
     */
    public function extract(array $request): void
    {
        $id = $this->getId();
        if (is_null($id)) {
            throw new \LogicException('Unit ID must be set before calling extract');
        }
        if ($_SERVER["REQUEST_METHOD"] !== 'GET' && !isset($request["media_id_{$id}"])) {
            throw new \InvalidArgumentException("media id is required for unit id {$id}");
        }
        $this->setField1($this->implodeUnitDataTrait($request["media_id_{$id}"] ?? ''));
        $this->setField2($this->implodeUnitDataTrait($request["media_caption_{$id}"] ?? ''));
        $this->setField3($this->implodeUnitDataTrait($request["media_alt_{$id}"] ?? ''));
        $this->setField4($this->implodeUnitDataTrait($request["media_enlarged_{$id}"] ?? ''));
        $this->setField5($this->implodeUnitDataTrait($request["media_use_icon_{$id}"] ?? ''));
        $this->setField7($this->implodeUnitDataTrait($request["media_link_{$id}"] ?? ''));
        [$size, $displaySize] = $this->extractUnitSizeTrait($this->implodeUnitDataTrait($request["media_size_{$id}"] ?? ''), $this::getUnitType());
        $this->setSize($size);
        $this->setField6($displaySize);
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if ($this->getField1() === '') {
            return false;
        }
        return true;
    }

    /**
     * ユニット複製時の専用処理
     *
     * @return void
     */
    public function handleDuplicate(): void
    {
    }

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    public function handleRemove(): void
    {
    }

    /**
     * キーワード検索用のワードを取得
     *
     * @return string
     */
    public function getSearchText(): string
    {
        return '';
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        return [];
    }

    /**
     * ユニットの描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    public function render(Template $tpl, array $vars, array $rootBlock): void
    {
        if ($this->getField1() === '') {
            return;
        }
        $varsRoot = $vars;
        $midAry = $this->explodeUnitDataTrait($this->getField1());
        $mediaCaptions = $this->explodeUnitDataTrait($this->getField2());
        $mediaAlts = $this->explodeUnitDataTrait($this->getField3());
        $mediaSizes = $this->explodeUnitDataTrait($this->getSize());
        $mediaAlign = $this->getAlign()->value;
        $mediaLarges = $this->explodeUnitDataTrait($this->getField4());
        $mediaUseIcons = $this->explodeUnitDataTrait($this->getField5());
        $displaySize = $this->getField6();
        $mediaLinks = $this->explodeUnitDataTrait($this->getField7());
        $eagerLoadedMedia = $this->getEagerLoadedMedia();
        $actualType = $this->getType();

        foreach ($midAry as $i => $mid) {
            $fx = $i === 0 ? '' : $i + 1;
            $mid = (int) $mid;
            $vars = [
                "anker" => $this->getAnker(),
            ];

            if (!isset($eagerLoadedMedia[$mid])) {
                continue;
            }
            $media = $eagerLoadedMedia[$mid];
            $path = $media['media_path'];
            $type = $media['media_type'];

            $vars["caption{$fx}"] = ($mediaCaptions[$i] ?? '') ?: $media['media_field_1'];
            $vars["alt{$fx}"] = ($mediaAlts[$i] ?? '') ?: $media['media_field_3'];
            if (!empty($media['media_field_4'])) {
                $vars["text{$fx}"] = $media['media_field_4'];
            }
            if (MediaHelper::isImageFile($type) || MediaHelper::isSvgFile($type)) {
                $vars += $this->renderImage($tpl, $i, $path, $media, $vars, $fx, $rootBlock, $mediaSizes, $mediaLarges, $mediaLinks);
            } elseif (MediaHelper::isFile($type)) {
                $vars += $this->renderFile($mid, $i, $path, $media, $vars, $fx, $mediaUseIcons, $mediaSizes);
            }
            $tpl->add(array_merge([
                'type' . $fx . '#' . $type,
                "unit#{$actualType}"
            ], $rootBlock), $vars);
            $varsRoot = [
                ...$varsRoot,
                "type{$fx}" => $type,
                ...(isset($vars["x{$fx}"]) ? ["rootWidth{$fx}" => $vars["x{$fx}"]] : []),
                ...(isset($vars["y{$fx}"]) ? ["rootHeight{$fx}" => $vars["y{$fx}"]] : []),
            ];
        }
        $varsRoot = $this->displaySizeStyleTrait($displaySize, $varsRoot);
        $varsRoot['align'] = $mediaAlign;
        $tpl->add(array_merge(["unit#{$actualType}"], $rootBlock), $varsRoot);
    }

    /**
     * 編集画面のユニット描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    public function renderEdit(Template $tpl, array $vars, array $rootBlock): void
    {
        $midAry = $this->explodeUnitDataTrait($this->getField1());
        $vars += ['type' => 'image'];
        $isMediaType = false;
        $eagerLoadedMedia = $this->getEagerLoadedMedia();

        foreach ($midAry as $i => $mid) {
            $mid = intval($mid);
            $fx = $i === 0 ? '' : $i + 1;
            if (isset($eagerLoadedMedia[$mid])) {
                $media = $eagerLoadedMedia[$mid];
            } else {
                $SQL = SQL::newSelect('media');
                $SQL->addWhereOpr('media_id', $mid);
                $media = Database::query($SQL->get(dsn()), 'row');
            }
            if (empty($media)) {
                $media = [
                    'media_type' => '',
                    'media_path' => '',
                    'media_image_size' => '',
                    'media_field_1' => '',
                    'media_field_2' => '',
                    'media_field_3' => '',
                    'media_field_4' => '',
                    'media_file_name' => '',
                    'media_thumbnail' => ''
                ];
            }
            $path = MediaHelper::urlencode($media['media_path']);
            if (isset($media['media_type']) && MediaHelper::isImageFile($media['media_type'])) {
                $isMediaType = true;
                $path .= MediaHelper::cacheBusting($media['media_update_date']);
            } elseif (isset($media['media_type']) && MediaHelper::isSvgFile($media['media_type'])) {
                $vars['type' . $fx] = 'svg';
                $path .= MediaHelper::cacheBusting($media['media_update_date']);
            } elseif ($media) {
                $vars['type' . $fx] = 'file';
            }
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $size = $media['media_image_size'];
            $sizes = explode(' x ', $size);
            $landscape = 'true';
            if (isset($sizes[0]) && isset($sizes[1])) {
                $landscape = $sizes[0] > $sizes[1] ? 'true' : 'false';
            }
            $vars += [
                "media_id{$fx}" => $mid,
                "caption{$fx}" => $media['media_field_1'],
                "link{$fx}" => $media['media_field_2'],
                "alt{$fx}" => $media['media_field_3'],
                "title{$fx}" => $media['media_field_4'],
                "type{$fx}" => $media['media_type'],
                "name{$fx}" => $media['media_file_name'],
                "path{$fx}" => $path,
                "tiny{$fx}" => $path !== '' ? otherSizeImagePath($path, 'tiny') : '',
                "landscape{$fx}" => $landscape,
                "media_pdf{$fx}" => 'no',
                "use_icon{$fx}" => 'false',
            ];
            if (!empty($ext)) {
                $vars["icon{$fx}"] = Common::resolveUrl('/' . DIR_OFFSET . pathIcon($ext));
            }
            if (!empty($media['media_thumbnail'])) {
                $vars["thumbnail{$fx}"] = MediaHelper::getPdfThumbnail($media['media_thumbnail']);
                $vars["media_pdf{$fx}"] = 'yes';
                $this->formatMultiLangUnitDataTrait($this->getField5(), $vars, 'use_icon');
            }
        }
        $this->formatMultiLangUnitDataTrait($this->getField4(), $vars, 'enlarged');
        $this->formatMultiLangUnitDataTrait($this->getField7(), $vars, 'override-link');
        $this->formatMultiLangUnitDataTrait($this->getField2(), $vars, 'override-caption');
        $this->formatMultiLangUnitDataTrait($this->getField3(), $vars, 'override-alt');

        // size select
        $size = $this->getSize();
        $this->renderSizeSelectTrait($this::getUnitType(), $this::getUnitType(), $size, $tpl, $rootBlock);

        // primary image
        if ($isMediaType) {
            $vars['primaryImageId'] = $this->getId();
            if ($this->isPrimaryImage()) {
                $vars['primaryImageChecked'] = config('attr_checked');
            }
        }
        $tpl->add(array_merge([$this::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'media_id' => $this->getField1(),
            'caption' => $this->getField2(),
            'alt' => $this->getField3(),
            'enlarged' => $this->getField4(),
            'use_icon' => $this->getField5(),
            'display_size' => $this->getField6(),
            'link' => $this->getField7()
        ];
    }

    /**
     * メディア画像の描画
     *
     * @param Template $tpl
     * @param int $index
     * @param string $path
     * @param array $media
     * @param array $vars
     * @param string $suffix
     * @param array $rootBlock
     * @param array $mediaSizes
     * @param array $mediaLarges
     * @param array $mediaLinks
     * @return array
     */
    protected function renderImage(Template $tpl, int $index, string $path, array $media, array $vars, string $suffix, array $rootBlock, array $mediaSizes, array $mediaLarges, array $mediaLinks)
    {
        $cacheBustingPath = $path . MediaHelper::cacheBusting($media['media_update_date']);
        $vars["path{$suffix}"] = MEDIA_LIBRARY_DIR . MediaHelper::urlencode($cacheBustingPath);
        $size = $mediaSizes[$index] ?? '';
        $unitLink = $mediaLinks[$index] ?? '';
        $link = $unitLink ? $unitLink : $media['media_field_2'];
        $url = false;
        $eid = $this->getEntryId();
        $type = $media['media_type'];
        $actualType = $this->getType();
        if (!MediaHelper::isSvgFile($type)) {
            $vars["image_utid{$suffix}"] = $this->getId();
        }
        $vars = $this->resolveImageSize($vars, $suffix, $media['media_image_size'], $size, $type, $path);
        if ($size !== '') {
            // 画像サイズ指定がある場合のみ、画像リサイズ用の変数を出力
            $vars["resizeWidth{$suffix}"] = $vars["x{$suffix}"];
            $vars["resizeHeight{$suffix}"] = $vars["y{$suffix}"];
        }
        if ($link) {
            $url = setGlobalVars($link);
        } elseif (isset($mediaLarges[$index]) && $mediaLarges[$index] !== 'no') {
            $url = MediaHelper::getImagePermalink($cacheBustingPath);
        }
        if (!empty($url) && isset($mediaLarges[$index]) && $mediaLarges[$index] !== 'no') {
            $varsLink = [
                "url{$suffix}" => $url,
                "link_eid{$suffix}" => $eid,
            ];
            if (!$link) {
                $varsLink["viewer{$suffix}"] = str_replace(
                    '{unit_eid}',
                    strval($eid),
                    config('entry_body_image_viewer')
                );
            }
            $tpl->add(array_merge([
                "link{$suffix}#front",
                "type{$suffix}#" . $media['media_type'],
                "unit#{$actualType}",
            ], $rootBlock), $varsLink);
            $tpl->add(array_merge([
                "link{$suffix}#rear",
                "type{$suffix}#" . $media['media_type'],
                "unit#{$actualType}",
            ], $rootBlock));
        }
        return $vars;
    }

    /**
     * メディアファイルの描画
     *
     * @param int $mid
     * @param int $index
     * @param string $path
     * @param array $media
     * @param array $vars
     * @param string $suffix
     * @param array $mediaUseIcons
     * @param array $mediaSizes
     * @return array
     */
    protected function renderFile(int $mid, int $index, string $path, array $media, array $vars, string $suffix, array $mediaUseIcons, array $mediaSizes): array
    {
        if ($media['media_status'] === '') {
            $url = MediaHelper::getFileOldPermalink($path, false);
        } else {
            $url = MediaHelper::getFilePermalink($mid, false);
        }
        $isPdfFile = strtolower($media['media_extension']) === 'pdf';
        $useIcon = isset($mediaUseIcons[$index]) ? $mediaUseIcons[$index] : 'no';
        $iconPath = pathIcon($media['media_extension']);

        $vars += [
            "url{$suffix}" => $url,
            "icon{$suffix}" => $iconPath,
            "use_icon{$suffix}" => $useIcon,
            "file_utid{$suffix}" => $this->getId(),
        ];
        if ($useIcon === 'yes' || !$isPdfFile) {
            list($iconWidth, $iconHeight) = $this->getIconSize($iconPath);
            $vars += [
                "x{$suffix}" => $iconWidth,
                "y{$suffix}" => $iconHeight,
            ];
        } else {
            if ($media['media_thumbnail'] !== '') {
                $vars["thumbnail{$suffix}"] = $media['media_thumbnail'];
                $size = $mediaSizes[$index] ?? '';
                $vars = $this->resolveImageSize($vars, $suffix, $media['media_image_size'], $size, 'file', $media['media_thumbnail']);
            }
        }
        return $vars;
    }

    /**
     * アイコンサイズを取得
     *
     * @param string $icon アイコン画像のパス
     * @return array{int, int}
     */
    private function getIconSize(string $icon): array
    {
        $defaultWidth = 70;
        $defaultHeight = 81;

        if (config('file_icon_size') === 'dynamic') {
            $xy = LocalStorage::getImageSize($icon);
            return [$xy[0] ?? $defaultWidth, $xy[1] ?? $defaultHeight];
        }

        return [$defaultWidth, $defaultHeight];
    }

    /**
     * 画像サイズを解決する
     *
     * @param array $vars
     * @param string $suffix
     * @param string $dbSize
     * @param string $configSize
     * @param string $mediaType
     * @param string $path
     * @return array
     */
    private function resolveImageSize(array $vars, string $suffix, string $dbSize, string $configSize, string $mediaType, string $path): array
    {
        $originalX = 0;
        $originalY = 0;
        $width = 0;
        $height = 0;

        if (strpos($dbSize, 'x') !== false) {
            list($tempX, $tempY) = explode('x', $dbSize);
            $originalX = intval(trim($tempX));
            $originalY = intval(trim($tempY));
        }
        if (strpos($configSize, 'x') !== false) {
            list($tempX, $tempY) = explode('x', $configSize);
            if (empty($originalX) || empty($originalY) || ($originalX >= $tempX && $originalY >= $tempY)) {
                $width = $tempX;
                $height = $tempY;
            } else {
                $width = $originalX;
                $height = $originalY;
            }
        } elseif ($originalX > 0 && $originalY > 0) {
            $tempX = $configSize;
            $tempY = intval(intval($tempX) * ($originalY / $originalX));
            if (!empty($tempX) && !empty($tempY) && $originalX >= $tempX && $originalY >= $tempY) {
                $width = $tempX;
                $height = $tempY;
            } else {
                $width = $originalX;
                $height = $originalY;
            }
        } elseif (MediaHelper::isSvgFile($mediaType)) {
            $width = $configSize;
            $height = $width;
            $vars["svg_utid{$suffix}"] = $this->getId();

            $doc = new DOMDocument();
            if ($doc->loadXML(file_get_contents(urldecode(MEDIA_LIBRARY_DIR . $path)))) {
                $svg = $doc->getElementsByTagName('svg');
                $item = $svg->item(0);
                if ($item !== null) {
                    $svgWidth = intval($item->getAttribute('width'));
                    $svgHeight = intval($item->getAttribute('height'));
                    if (empty($svgWidth) || empty($svgHeight)) {
                        if ($viewBox = $item->getAttribute('viewBox')) {
                            $viewBox = explode(' ', $viewBox);
                            $svgWidth = intval($viewBox[2]);
                            $svgHeight = intval($viewBox[3]);
                        }
                    }
                    if ($svgWidth > 0 && $svgHeight > 0) {
                        $height = intval(intval($width) * ($svgHeight / $svgWidth));
                    }
                }
            }
        } else {
            $width = $configSize;
            $height = '';
        }

        $vars["x{$suffix}"] = $width;
        $vars["y{$suffix}"] = $height;

        return $vars;
    }
}
