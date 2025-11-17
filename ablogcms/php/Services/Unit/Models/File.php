<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\ExportEntry;
use Acms\Services\Unit\Contracts\StaticExport;
use Acms\Services\Unit\Contracts\AssetProvider;
use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Entry;
use Acms\Services\Unit\Services\File\FileDataExtractor;
use Acms\Services\Unit\Services\File\FileManager;
use Acms\Services\Common\MimeTypeValidator;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\UnitMultiLangTrait;
use Template;

/**
 * @phpstan-import-type FileData from FileDataExtractor
 *
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class File extends Model implements AssetProvider, StaticExport, ExportEntry, AlignableUnitInterface, AnkerUnitInterface
{
    use \Acms\Traits\Common\AssetsTrait;
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use UnitMultiLangTrait;

    /**
     * ユニットの独自データ
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * リクエストデータから抽出したファイルデータ
     * @var FileData|null
     */
    private $fileData = null;

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'file_caption' => $this->getCaption()[0] ?? '',
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
     * ファイルのパスを配列で取得
     *
     * @inheritDoc
     */
    public function getFilePaths(): array
    {
        return $this->explodeUnitDataTrait($this->getField2());
    }

    /**
     * ファイルのパスを配列でセット
     *
     * @inheritDoc
     */
    public function setFilePaths($paths): void
    {
        $this->setField2($this->implodeUnitDataTrait($paths));
    }

    /**
     * 静的書き出しで書き出しを行うアセットのパス配列
     *
     * @return array
     */
    public function outputAssetPaths(): array
    {
        return array_map(function (string $path) {
            return ARCHIVES_DIR . $path;
        }, $this->getFilePaths());
    }

    /**
     * エントリーのエクスポートでエクスポートするアセットを返却
     *
     * @return string[]
     */
    public function exportArchivesFiles(): array
    {
        return $this->getFilePaths();
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
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        return 'file';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('ファイル');
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
        $this->setCaption(config("{$configKeyPrefix}field_1", '', $configIndex));
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
        $extractor = new FileDataExtractor($id);

        // データ抽出（ファイル保存処理は行わない）
        $data = $extractor->extract($request);

        if ($data['type'] === 'multilang') {
            // キャプションの設定
            $captions = array_map(function (array $fileRequest) {
                return $fileRequest['caption'];
            }, $data['file_requests']);
            $this->setCaption($captions);
            // 初期値として既存のファイルパスを保持（新しいファイルは無視）
            // saveFilesでファイル保存処理を行うと、この値は上書きされる
            $paths = [];
            foreach ($data['file_requests'] as $fileRequest) {
                $paths[] = $fileRequest['old_path'];
            }
            $this->setFilePaths($paths);
        } else {
            // キャプションの設定
            $fileRequest = $data['file_request'];
            $this->setCaption($fileRequest['caption']);
            // 初期値として既存のファイルパスを保持（新しいファイルは無視）
            // saveFilesでファイル保存処理を行うと、この値は上書きされる
            $this->setFilePaths($fileRequest['old_path']);
        }

        $this->fileData = $data;
    }

    /**
     * @inheritDoc
     */
    public function saveFiles(array $post, bool $removeOld = true): void
    {
        if (is_null($this->fileData)) {
            throw new \LogicException('File data must be set before calling saveFiles');
        }

        $manager = new FileManager($removeOld);
        // ファイル処理（保存・アップロード・削除）
        $results = $manager->processFiles($this->fileData);

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

        // エントリーの新バージョン用にファイルを複製
        if (Entry::isNewVersion()) {
            $currentFilePaths = $this->getFilePaths();
            if (count($currentFilePaths) > 0) {
                $newFilePaths = $manager->duplicateFilesForNewVersion($currentFilePaths);
                $this->setFilePaths($newFilePaths);
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
        $filePaths = $this->getFilePaths();
        $newFilePaths = $this->duplicateFilesTrait($filePaths);
        $this->setFilePaths($newFilePaths);
    }

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    public function handleRemove(): void
    {
        $filePaths = $this->getFilePaths();
        $this->removeFileAssetsTrait($filePaths);
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

        foreach ($paths as $i => $val) {
            $fx = $i === 0 ? '' : $i + 1;
            $path = ARCHIVES_DIR . $val;
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $icon = pathIcon($ext);
            if (!LocalStorage::exists($icon)) {
                continue;
            }
            $vars += [
                'path' . $fx => $path,
                'icon' . $fx => $icon,
                'x' . $fx => 70,
                'y' . $fx => 81,
            ];
            if (config('file_icon_size') === 'dynamic') {
                $xy = LocalStorage::getImageSize($icon);
                $vars['x' . $fx] = $xy[0] ?? 70;
                $vars['y' . $fx] = $xy[1] ?? 81;
            }
        }
        $vars['caption'] = $this->getCaption();
        $vars['align'] = $this->getAlign()->value;
        $vars['anker'] = $this->getAnker();
        $this->formatMultiLangUnitDataTrait($vars['caption'], $vars, 'caption');

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
        if ($path = $this->getField2()) {
            $vars['old'] = $path;
            $length = count($this->explodeUnitDataTrait($path));
            $this->formatMultiLangUnitDataTrait($vars['old'], $vars, 'old');

            for ($i = 0; $i < $length; $i++) {
                if ($i === 0) {
                    $fx = '';
                } else {
                    $fx = $i + 1;
                }

                if (!isset($vars['old' . $fx])) {
                    continue;
                }
                $path = $vars['old' . $fx];
                $vars['basename' . $fx] = LocalStorage::mbBasename($path);

                $mimeValidator = new MimeTypeValidator();
                $e = preg_replace('@.*\.(?=[^.]+$)@', '', $path);
                $t = null;
                if ($mimeValidator->validateAllowedExtension($e, configArray('file_extension_document'))) {
                    $t = 'document';
                } elseif ($mimeValidator->validateAllowedExtension($e, configArray('file_extension_archive'))) {
                    $t = 'archive';
                } elseif ($mimeValidator->validateAllowedExtension($e, configArray('file_extension_movie'))) {
                    $t = 'movie';
                } elseif ($mimeValidator->validateAllowedExtension($e, configArray('file_extension_audio'))) {
                    $t = 'audio';
                }
                $fileList = LocalStorage::getFileList(THEMES_DIR . 'system/' . IMAGES_DIR . 'fileicon/');
                $icon = $t;
                $pattern = '/' . strtolower($e) . '.*$/';
                foreach ($fileList as $filePath) {
                    if (preg_match($pattern, strtolower($filePath))) {
                        $icon = $e;
                        break;
                    }
                }
                $vars['icon' . $fx]   = $icon;
                $vars['type' . $fx]   = $icon;
            }

            $vars['deleteId'] = $this->getId();
        }
        $vars['caption'] = $this->getCaption();
        $this->formatMultiLangUnitDataTrait($vars['caption'], $vars, 'caption');

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
            'caption' => $this->getField1(),
            'path' => $this->getField2(),
        ];
    }
}
