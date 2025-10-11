<?php

namespace Acms\Services\Unit\Services\Image;

use Acms\Services\Facades\PublicStorage;
use ACMS_POST_Image;
use ACMS_Hook;

/**
 * @phpstan-import-type ImageData from ImageDataExtractor
 * @phpstan-import-type SingleImageData from ImageDataExtractor
 * @phpstan-import-type MultiLangImageData from ImageDataExtractor
 *
 * @phpstan-type ImageProcessResult array{
 *  path: string,
 *  edit: 'none'|'deleteLarge'|'rotate270'|'rotate90'|'rotate180'|'delete'
 * }
 */
class ImageFileManager
{
    use \Acms\Traits\Common\AssetsTrait;

    /**
     * @var \Acms\Services\Common\HookFactory
     */
    private $hook;

    /**
     * @var string
     */
    private $id;

    /**
     * @var \ACMS_POST_Image
     */
    private $imageHelper;

    /**
     * コンストラクタ
     * @param string $id ユニットID
     * @param bool $removeOld 古い画像を削除するかどうか
     */
    public function __construct(string $id, bool $removeOld = true)
    {
        $this->id = $id;
        $this->hook = ACMS_Hook::singleton();
        $this->imageHelper = new ACMS_POST_Image($removeOld);
    }

    /**
     * 画像ファイルを保存
     *
     * @param ImageData $data 画像データ
     * @return ImageProcessResult[]
     */
    public function processImages(array $data): array
    {
        if ($data['type'] === 'multilang') {
            // 多言語ユニット
            /** @var MultiLangImageData $data */
            return $this->processMultilingualImages($data);
        } else {
            // 通常ユニット
            /** @var SingleImageData $data */
            return $this->processSingleImage($data);
        }
    }

    /**
     * 多言語画像を保存
     * @param MultiLangImageData $data 画像データ
     * @return ImageProcessResult[]
     */
    private function processMultilingualImages(array $data): array
    {
        $results = [];

        foreach ($data['requests'] as $i => $request) {
            $dataUrl = $request['dataUrl'];
            if ($dataUrl !== '') {
                ACMS_POST_Image::base64DataToImage($dataUrl, "image_file_{$this->id}", $i);
            }
        }

        foreach ($data['requests'] as $i => $request) {
            $result = [
                'path' => $request['old'],
                'edit' => $request['edit']
            ];

            $old = $this->validateRemovePath('image', $request['old']) ? $request['old'] : '';

            $tmpFile = $_FILES["image_file_{$this->id}"]['tmp_name'][$i] ?? '';

            $imageData = $this->imageHelper->buildAndSave(
                $old,
                $tmpFile,
                $data['size'],
                $request['edit'],
                $data['oldSize']
            );

            if ($imageData !== null) {
                $result['path'] = $imageData['path'];
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * 単一画像を保存
     * @param SingleImageData $data 画像データ
     * @return ImageProcessResult[]
     */
    private function processSingleImage(array $data): array
    {
        // dataUrlをファイルに変換（$_FILES["image_file_{$id}"]に格納）
        $dataUrl = $data['request']['dataUrl'];
        if ($dataUrl !== '') {
            ACMS_POST_Image::base64DataToImage($dataUrl, "image_file_{$this->id}");
        }
        $file = $_FILES["image_file_{$this->id}"] ?? null;
        $tmpFile = ($file !== null && is_array($file['tmp_name']) && isset($file['tmp_name'][0])) ? $file['tmp_name'][0] : $file['tmp_name'] ?? '';
        $request = $data['request'];
        $result = [
            'path' => $request['old'],
            'edit' => $request['edit']
        ];

        $old = $this->validateRemovePath('image', $request['old']) ? $request['old'] : '';

        $imageData = $this->imageHelper->buildAndSave(
            $old,
            $tmpFile,
            $data['size'],
            $request['edit'],
            $data['oldSize']
        );

        if ($imageData !== null) {
            $result['path'] = $imageData['path'];
        }

        return [$result];
    }

    /**
     * 画像のバリエーションを作成
     * @param string $path 画像のパス
     * @return void
     */
    public function createImageVariations(string $path): void
    {
        $info = pathinfo($path);
        $dirname = $info['dirname'] === '' ? '' : $info['dirname'] . '/';
        PublicStorage::makeDirectory(ARCHIVES_DIR . $dirname);

        $ext = $info['extension'] === '' ? '' : '.' . $info['extension'];
        $newPath = $dirname . uniqueString() . $ext;

        $path = ARCHIVES_DIR . $path;
        $large = otherSizeImagePath($path, 'large');
        $tiny = otherSizeImagePath($path, 'tiny');
        $square = otherSizeImagePath($path, 'square');

        $newPath = ARCHIVES_DIR . $newPath;
        $newLarge = otherSizeImagePath($newPath, 'large');
        $newTiny = otherSizeImagePath($newPath, 'tiny');
        $newSquare = otherSizeImagePath($newPath, 'square');

        copyFile($path, $newPath, true);
        copyFile($large, $newLarge, true);
        copyFile($tiny, $newTiny, true);
        copyFile($square, $newSquare, true);

        if (HOOK_ENABLE) {
            $this->hook->call('mediaCreate', $newPath);
            $this->hook->call('mediaCreate', $newLarge);
            $this->hook->call('mediaCreate', $newTiny);
            $this->hook->call('mediaCreate', $newSquare);
        }
    }
}
