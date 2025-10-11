<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\ExportEntry;
use Acms\Services\Unit\Contracts\ImageUnit;
use Acms\Services\Unit\Contracts\UnitListModule;
use Acms\Services\Unit\Contracts\AssetProvider;
use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Services\Unit\Services\Image\ImageDataExtractor;
use Acms\Services\Unit\Services\Image\ImageFileManager;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Common;
use Acms\Traits\Unit\UnitMultiLangTrait;
use Template;
use Acms\Traits\Unit\SizeableUnitTrait;
use Acms\Services\Unit\Contracts\SizeableUnitInterface;

/**
 * @phpstan-import-type ImageData from ImageDataExtractor
 *
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Image extends Model implements ImageUnit, AssetProvider, UnitListModule, ExportEntry, AlignableUnitInterface, AnkerUnitInterface, SizeableUnitInterface
{
    use \Acms\Traits\Common\AssetsTrait;
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use SizeableUnitTrait;
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
     * 編集アクション
     * @var string
     */
    private $editAction = '';

    /**
     * リクエストデータから抽出した画像データ
     * @var ImageData|null
     */
    private $imageData = null;

    /**
     * @inheritDoc
     */
    public function getFilePaths(): array
    {
        return $this->explodeUnitDataTrait($this->getField2());
    }

    /**
     * メイン画像のパスを取得。メディアの場合メディアIDを取得
     *
     * @inheritDoc
     */
    public function getPaths(): array
    {
        return $this->getFilePaths();
    }

    /**
     * @inheritDoc
     */
    public function setFilePaths($paths): void
    {
        $this->setField2($this->implodeUnitDataTrait($paths));
    }

    /**
     * メイン画像のAltを取得
     *
     * @inheritDoc
     */
    public function getAlts(): array
    {
        return $this->explodeUnitDataTrait($this->getField4());
    }

    /**
     * メイン画像のキャプションを取得
     *
     * @inheritDoc
     */
    public function getCaptions(): array
    {
        return $this->explodeUnitDataTrait($this->getField1());
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
        $paths = $this->getFilePaths();
        if (count($paths) === 0) {
            // ファイルパスが空の場合はメイン画像にできない
            return false;
        }
        if (
            array_all($paths, function (string $path) {
                return $path === '';
            })
        ) {
            // すべてのファイルパスが空文字の場合はメイン画像にできない
            return false;
        }
        return true;
    }

    /**
     * エントリーのエクスポートでエクスポートするアセットを返却
     *
     * @return string[]
     */
    public function exportArchivesFiles(): array
    {
        $paths = $this->explodeUnitDataTrait($this->getField2());
        $exportFiles = [];
        foreach ($paths as $path) {
            $exportFiles[] = $path;
            $exportFiles[] = otherSizeImagePath($path, 'large');
            $exportFiles[] = otherSizeImagePath($path, 'tiny');
            $exportFiles[] = otherSizeImagePath($path, 'square');
        }
        return $exportFiles;
    }

    /**
     * エントリーのエクスポートでエクスポートするメディアIDを返却
     *
     * @return int[]
     */
    public function exportMediaIds(): array
    {
        return [];
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
        $vars = [];
        $path = $this->explodeUnitDataTrait($this->getField2());
        $normal = $path[0] ?? $path;
        $tiny = preg_replace('@(^|/)(?=[^/]+$)@', '$1tiny-', $normal);
        $large = preg_replace('@(^|/)(?=[^/]+$)@', '$1large-', $normal);
        $square = preg_replace('@(^|/)(?=[^/]+$)@', '$1square-', $normal);

        $vars['tiny'] = $tiny ? Common::resolveUrl($tiny, ARCHIVES_DIR) : null;
        $vars['normal'] = Common::resolveUrl($normal, ARCHIVES_DIR);
        if (PublicStorage::isFile(ARCHIVES_DIR . $large)) {
            $vars['large'] = Common::resolveUrl($large, ARCHIVES_DIR);
        }
        if (PublicStorage::isFile(ARCHIVES_DIR . $square)) {
            $vars['square'] = Common::resolveUrl($square, ARCHIVES_DIR);
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
        return 'image';
    }

    /**
     * ユニットラベルを取得
     *
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('画像');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'image_caption' => $this->getCaption()[0] ?? '',
            'image_link' => $this->getLink()[0] ?? '',
            'image_alt' => $this->getAlt()[0] ?? '',
            'image_size' => $this->getSize(),
            'image_edit' => $this->getEditAction(),
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
     * ユニットのデフォルト値をセット
     *
     * @param string $configKeyPrefix
     * @param int $configIndex
     * @return void
     */
    public function setDefault(string $configKeyPrefix, int $configIndex): void
    {
        $this->setEditAction(config("{$configKeyPrefix}edit", '', $configIndex));
        $this->setCaption(config("{$configKeyPrefix}field_1", '', $configIndex));
        $this->setFilePaths(config("{$configKeyPrefix}field_2", '', $configIndex));
        $this->setField3(config("{$configKeyPrefix}field_3", '', $configIndex));
        $this->setField4(config("{$configKeyPrefix}field_4", '', $configIndex));
        $this->setField6(config("{$configKeyPrefix}field_6", '', $configIndex));
    }

    /**
     * POSTデータからユニット独自データを抽出
     *
     * @param array $request
     * @return void
     */
    public function extract(array $request): void
    {
        $id = $this->getId();
        if (is_null($id)) {
            throw new \LogicException('Unit ID must be set before calling extract');
        }

        // データ抽出
        $extractor = new ImageDataExtractor($id);
        $data = $extractor->extract($request);

        if ($data['type'] === 'multilang') {
            $imageRequests = $data['requests'];
            $oldPaths = array_map(function (array $request) {
                return $request['old'];
            }, $imageRequests);
            // 初期値として既存のファイルパスを保持（新しいファイルは無視）
            // saveFilesでファイル保存処理を行うと、この値は上書きされる
            $this->setFilePaths($oldPaths);
            $captions = array_map(function (array $request) {
                return $request['caption'];
            }, $imageRequests);
            $this->setCaption($captions);
            $alts = array_map(function (array $request) {
                return $request['alt'];
            }, $imageRequests);
            $this->setAlt($alts);
            $links = array_map(function (array $request) {
                return $request['link'];
            }, $imageRequests);
            $this->setLink($links);
            $exifs = array_map(function (array $request) {
                return $request['exif'];
            }, $imageRequests);
            $this->setExif($exifs);
        } else {
            $imageRequest = $data['request'];
            // 初期値として既存のファイルパスを保持（新しいファイルは無視）
            // saveFilesでファイル保存処理を行うと、この値は上書きされる
            $this->setFilePaths($imageRequest['old']);
            $this->setCaption($imageRequest['caption']);
            $this->setAlt($imageRequest['alt']);
            $this->setLink($imageRequest['link']);
            $this->setExif($imageRequest['exif']);
        }

        // サイズの設定
        [$size, $displaySize] = $this->extractUnitSizeTrait($data['size'], $this::getUnitType());
        $this->setSize($size);
        $this->setField5($displaySize);

        $this->imageData = $data;
    }

    /**
     * @inheritDoc
     */
    public function saveFiles(array $post, bool $removeOld = true): void
    {
        if (is_null($this->imageData)) {
            throw new \LogicException('Image data must be set before calling saveFiles');
        }
        if (is_null($this->getId())) {
            throw new \LogicException('Unit ID must be set before calling saveFiles');
        }

        $manager = new ImageFileManager($this->getId(), $removeOld);
        $results = $manager->processImages($this->imageData);

        $filePaths = [];
        foreach ($results as $result) {
            if ($result['edit'] === 'delete') {
                // 削除モードの場合はファイルパスを空文字にする
                $filePaths[] = '';
                continue;
            }
            $filePaths[] = $result['path']; // アップロードされたファイルのパス or 既存のファイルのパス
        }

        $this->setFilePaths($filePaths);

        // 新バージョンの場合、画像バリエーションを作成
        if (Entry::isNewVersion()) {
            $paths = $this->getFilePaths();
            foreach ($paths as $path) {
                if (!in_array($path, Entry::getUploadedFiles(), true)) {
                    $manager->createImageVariations($path);
                }
            }
        }
    }
    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if (count($this->getFilePaths()) === 0) {
            // ファイルパスが空の場合は保存できない
            return false;
        }
        if (
            array_all($this->getFilePaths(), function (string $path) {
                return $path === '';
            })
        ) {
            // すべてのファイルパスが空文字の場合は保存できない
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
        $imagePaths = $this->getFilePaths();
        $newImagePaths = $this->duplicateImagesTrait($imagePaths);
        $this->setFilePaths($newImagePaths);
    }

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    public function handleRemove(): void
    {
        $imagePaths = $this->getFilePaths();
        $this->removeImageAssetsTrait($imagePaths);
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
        $paths = $this->getFilePaths();
        if (count($paths) === 0) {
            return;
        }

        foreach ($paths as $i => $path_) {
            if ($i === 0) {
                $i = '';
            } else {
                $i++;
            }
            $path = ARCHIVES_DIR . $path_;
            $xy = PublicStorage::getImageSize($path);
            $vars['path' . $i] = $path;
            $vars['x' . $i] = $xy[0] ?? '';
            $vars['y' . $i] = $xy[1] ?? '';
        }
        $vars['alt'] = $this->getAlt();
        $vars['exif'] = $this->getExif();
        $vars = $this->displaySizeStyleTrait($this->getField5(), $vars);
        $vars['caption'] = $this->getCaption();
        $vars['align'] = $this->getAlign()->value;
        $vars['anker'] = $this->getAnker();

        /** @var string[] $linkAry */
        $linkAry = $this->getLink();
        $path = '';
        foreach ($paths as $i => $path_) {
            $j = $i === 0 ? '' : $i + 1;
            $link_ = $linkAry[$i] ?? '';
            $eid = $this->getEntryId();
            if ($link_ === '') {
                if ($paths[$i]) {
                    $path = ARCHIVES_DIR . $paths[$i];
                } else {
                    $path = ARCHIVES_DIR . $this->getFilePaths()[0];
                }
                $name = PublicStorage::mbBasename($path);
                $large = substr($path, 0, strlen($path) - strlen($name)) . 'large-' . $name;
                if ($xy = PublicStorage::getImageSize($large)) {
                    $tpl->add(
                        array_merge(['link' . $j . '#front', 'unit#' . $this->getType()], $rootBlock),
                        [
                            'url' . $j => BASE_URL . $large,
                            'viewer' . $j => str_replace('{unit_eid}', strval($eid), config('entry_body_image_viewer')),
                            'caption' . $j => $this->getCaption()[$i] ?? '',
                            'link_eid' . $j => $eid
                        ]
                    );
                    $tpl->add(array_merge(['link' . $j . '#rear', 'unit#' . $this->getType()], $rootBlock));
                }
            } else {
                $tpl->add(array_merge(['link' . $j . '#front', 'unit#' . $this->getType()], $rootBlock), [
                    'url' . $j => $link_,
                ]);
                $tpl->add(array_merge(['link' . $j . '#rear', 'unit#' . $this->getType()], $rootBlock));
            }
        }
        if ($path !== '') {
            $tiny = otherSizeImagePath($path, 'tiny');
            if ($xy = PublicStorage::getImageSize($tiny)) {
                $vars['tinyPath'] = $tiny;
                $vars['tinyX'] = $xy[0] ?? '';
                $vars['tinyY'] = $xy[1] ?? '';
            }
            $square = otherSizeImagePath($path, 'square');
            $squareImgSize = config('image_size_square');
            if (PublicStorage::isFile($square)) {
                $vars['squarePath'] = $square;
                $vars['squareX'] = $squareImgSize;
                $vars['squareY'] = $squareImgSize;
            }
            $large = otherSizeImagePath($path, 'large');
            if ($xy = PublicStorage::getImageSize($large)) {
                $vars['largePath'] = $large;
                $vars['largeX'] = $xy[0] ?? '';
                $vars['largeY'] = $xy[1] ?? '';
            }
        }
        foreach ($vars as $key => $val) {
            $this->formatMultiLangUnitDataTrait($val, $vars, $key);
        }
        $tpl->add(array_merge(['unit#' . $this->getType()], $rootBlock), $vars);
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
        $size = $this->getSize();
        $path = $this->getField2();
        $this->renderSizeSelectTrait(static::getUnitType(), static::getUnitType(), $size, $tpl, $rootBlock);

        $vars += [
            'old' => $path,
            'size_old' => $size . ':' . $this->getField5(),
            'caption' => $this->getCaption(),
            'link' => $this->getLink(),
            'alt' => $this->getAlt(),
            'exif' => $this->getExif(),
        ];
        $this->formatMultiLangUnitDataTrait($vars['old'], $vars, 'old');
        $this->formatMultiLangUnitDataTrait($vars['caption'], $vars, 'caption');
        $this->formatMultiLangUnitDataTrait($vars['exif'], $vars, 'exif');
        $this->formatMultiLangUnitDataTrait($vars['link'], $vars, 'link');
        $this->formatMultiLangUnitDataTrait($vars['alt'], $vars, 'alt');

        if ($editAction = $this->getEditAction()) {
            $vars['edit:selected#' . $editAction] = config('attr_selected');
        }
        // tiny and large
        if ($path) {
            $nXYAry     = [];
            $tXYAry     = [];
            $tinyAry    = [];
            $lXYAry     = [];
            foreach ($this->getFilePaths() as $normal) {
                $nXY   = PublicStorage::getImageSize(ARCHIVES_DIR . $normal);
                $tiny  = preg_replace('@[^/]+$@', 'tiny-$0', $normal) ?? '';
                $large = preg_replace('@[^/]+$@', 'large-$0', $normal) ?? '';
                $tXY   = PublicStorage::getImageSize(ARCHIVES_DIR . $tiny);
                if ($lXY = PublicStorage::getImageSize(ARCHIVES_DIR . $large)) {
                    $lXYAry['x'][] = $lXY[0];
                    $lXYAry['y'][] = $lXY[1];
                } else {
                    $lXYAry['x'][] = '';
                    $lXYAry['y'][] = '';
                }
                $nXYAry['x'][] = $nXY[0] ?? '';
                $nXYAry['y'][] = $nXY[1] ?? '';
                $tXYAry['x'][] = $tXY[0] ?? '';
                $tXYAry['y'][] = $tXY[1] ?? '';
                $tinyAry[] = $tiny;
            }
            $popup = otherSizeImagePath($path, 'large');
            if (!PublicStorage::getImageSize(ARCHIVES_DIR . $popup)) {
                $popup = $path;
            }
            $vars += [
                'tiny' => $this->implodeUnitDataTrait($tinyAry),
                'tinyX' => $tXYAry['x'],
                'tinyY' => $tXYAry['y'],
                'popup' => $popup,
                'normalX' => $nXYAry['x'],
                'normalY' => $nXYAry['y'],
                'largeX' => $lXYAry['x'],
                'largeY' => $lXYAry['y'],
            ];
            $this->formatMultiLangUnitDataTrait($vars['tiny'], $vars, 'tiny');
            $this->formatMultiLangUnitDataTrait($vars['tinyX'], $vars, 'tinyX');
            $this->formatMultiLangUnitDataTrait($vars['popup'], $vars, 'popup');
            $this->formatMultiLangUnitDataTrait($vars['normalX'], $vars, 'normalX');
            $this->formatMultiLangUnitDataTrait($vars['normalY'], $vars, 'normalY');
            $this->formatMultiLangUnitDataTrait($vars['largeX'], $vars, 'largeX');
            $this->formatMultiLangUnitDataTrait($vars['largeY'], $vars, 'largeY');

            foreach ($vars as $key => $val) {
                if ($val == '') {
                    unset($vars[$key]);
                }
            }
        } else {
            $tpl->add(array_merge(['preview#none', static::getUnitType()], $rootBlock));
        }
        // rotate
        if (function_exists('imagerotate')) {
            $count = count($this->getFilePaths());
            for ($i = 0; $i < $count; $i++) {
                if ($i === 0) {
                    $n = '';
                } else {
                    $n = $i + 1;
                }
                $tpl->add(array_merge(['rotate' . $n, static::getUnitType()], $rootBlock));
            }
        }
        // primary image
        $vars['primaryImageId'] = $this->getId();
        if ($this->isPrimaryImage()) {
            $vars['primaryImageChecked'] = config('attr_checked');
        }
        $tpl->add(array_merge([static::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'caption' => $this->getField1(),
            'path' => $this->getField2(),
            'link' => $this->getField3(),
            'alt' => $this->getField4(),
            'exif' => $this->getField6(),
            'display_size' => $this->getField5()
        ];
    }

    /**
     * キャプションを取得
     *
     * @return string[]
     */
    public function getCaption(): array
    {
        return $this->explodeUnitDataTrait($this->getField1());
    }

    /**
     * キャプションを設定
     *
     * @param string[]|string $caption
     * @return void
     */
    public function setCaption($caption): void
    {
        $this->setField1($this->implodeUnitDataTrait($caption));
    }

    /**
     * 代替テキストを取得
     *
     * @return string[]
     */
    public function getAlt(): array
    {
        return $this->explodeUnitDataTrait($this->getField4());
    }

    /**
     * 代替テキストを設定
     *
     * @param string[]|string $alt
     * @return void
     */
    public function setAlt($alt): void
    {
        $this->setField4($this->implodeUnitDataTrait($alt));
    }

    /**
     * リンクを取得
     *
     * @return string[]
     */
    public function getLink(): array
    {
        return $this->explodeUnitDataTrait($this->getField3());
    }

    /**
     * リンクを設定
     *
     * @param string[]|string $link
     * @return void
     */
    public function setLink($link): void
    {
        $this->setField3($this->implodeUnitDataTrait($link));
    }

    /**
     * EXIFデータを取得
     *
     * @return string[]
     */
    public function getExif(): array
    {
        return $this->explodeUnitDataTrait($this->getField6());
    }

    /**
     * EXIFデータを設定
     *
     * @param string[]|string $exif
     * @return void
     */
    public function setExif($exif): void
    {
        $this->setField6($this->implodeUnitDataTrait($exif));
    }

    /**
     * edit action getter
     *
     * @return string
     */
    public function getEditAction(): string
    {
        return $this->editAction;
    }

    /**
     * edit action setter
     *
     * @param string $editAction
     * @return void
     */
    public function setEditAction(string $editAction): void
    {
        $this->editAction = $editAction;
    }
}
