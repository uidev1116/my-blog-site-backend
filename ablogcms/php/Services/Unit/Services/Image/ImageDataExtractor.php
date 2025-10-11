<?php

namespace Acms\Services\Unit\Services\Image;

use ACMS_POST_Image;

/**
 * @phpstan-type ImageRequest = array{
 *   caption: string,
 *   old: string,
 *   edit: 'none'|'deleteLarge'|'rotate270'|'rotate90'|'rotate180'|'delete',
 *   link: string,
 *   alt: string,
 *   exif: string,
 *   dataUrl: string,
 * }
 *
 * @phpstan-type SingleImageData array{
 *   type: 'single',
 *   size: string,
 *   oldSize: string,
 *   request: ImageRequest,
 * }
 *
 * @phpstan-type MultiLangImageData array{
 *   type: 'multilang',
 *   size: string,
 *   oldSize: string,
 *   requests: array<int, ImageRequest>,
 * }
 *
 * @phpstan-type ImageData SingleImageData|MultiLangImageData
 */
class ImageDataExtractor
{
    /**
     * @var string
     */
    private $id;

    /**
     * コンストラクタ
     * @param string $id ユニットID
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }
    /**
     * リクエストデータから画像ユニットのデータを抽出
     *
     * @param array $request リクエストデータ
     * @return ImageData
     */
    public function extract(array $request): array
    {
        $id = $this->id;
        /** @var string[]|string|null $captions */
        $captions = $request["image_caption_{$this->id}"] ?? null;
        if (is_array($captions)) {
            // 多言語ユニット
            /** @var MultiLangImageData $data */
            $data = [
                'type' => 'multilang',
                'size' => $request["image_size_{$id}"] ?? '',
                'oldSize' => $request["old_image_size_{$id}"] ?? '',
                'requests' => array_map(function ($caption, $index) use ($request, $id) {
                    return [
                        'caption' => $caption,
                        'old' => $request["image_old_{$id}"][$index] ?? '',
                        'edit' => $request["image_edit_{$id}"][$index] ?? '',
                        'link' => $request["image_link_{$id}"][$index] ?? '',
                        'alt' => $request["image_alt_{$id}"][$index] ?? '',
                        'exif' => $request["image_exif_{$id}"][$index] ?? '',
                        'dataUrl' => $request["image_file_{$id}"][$index] ?? '',
                    ];
                }, $captions, array_keys($captions)),
            ];
        } else {
            // dataUrlをファイルに変換（$_FILES["image_file_{$id}"]に格納）
            $dataUrl = is_array($request["image_file_{$id}"] ?? null) ? $request["image_file_{$id}"][0] : $request["image_file_{$id}"] ?? '';
            /** @var SingleImageData $data */
            $data = [
                'type' => 'single',
                'size' => $request["image_size_{$id}"] ?? '',
                'oldSize' => $request["old_image_size_{$id}"] ?? '',
                'request' => [
                    'caption' => $captions ?? '',
                    'old' => $request["image_old_{$id}"] ?? '',
                    'edit' => $request["image_edit_{$id}"] ?? '',
                    'link' => $request["image_link_{$id}"] ?? '',
                    'alt' => $request["image_alt_{$id}"] ?? '',
                    'exif' => $request["image_exif_{$id}"] ?? '',
                    'dataUrl' => $dataUrl,
                ],
            ];
        }

        return $data;
    }
}
