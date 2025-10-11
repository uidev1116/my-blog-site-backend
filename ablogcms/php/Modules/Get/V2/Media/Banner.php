<?php

namespace Acms\Modules\Get\V2\Media;

use Acms\Modules\Get\V2\Base;
use Acms\Services\Facades\Media;
use Acms\Modules\Get\Helpers\MediaHelper;
use Field;

class Banner extends Base
{
    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();
        $mediaHelper = new MediaHelper([]);
        $items = $mediaHelper->getMediaBannerData($config);
        $mediaList = $this->loadMediaList($items);

        return [
            'items' => $this->normalizeItems($items, $mediaList),
            'moduleFields' => $this->buildModuleField(),
        ];
    }

    protected function loadMediaList(array $items): array
    {
        $mediaIds = array_values(array_filter(array_map(function ($item) {
            return (int) ($item['banner#img']['mid'] ?? 0);
        }, $items), function ($id) {
            return $id !== 0;
        }));
        return Media::mediaEagerLoad($mediaIds);
    }

    protected function normalizeItems(array $items, array $mediaList): array
    {
        $result = [];
        foreach ($items as $item) {
            $type = array_key_first($item);

            if ($type === 'banner#img') {
                $result[] = $this->processImageItem($item[$type], $mediaList);
            } elseif ($type === 'banner#src') {
                $result[] = [
                    'type' => 'source',
                    'src' => $item[$type]['src'] ?? '',
                ];
            }
        }
        return $result;
    }

    protected function processImageItem(array $data, array $mediaList): array
    {
        $mediaData = [];
        if ($mediaId = $data['mid'] ?? null) {
            $mediaField = new Field();
            $mediaField->set("banner@media", $mediaId);
            Media::injectMediaField($mediaField, $mediaList, ['banner']);
            $media = $this->buildFieldTrait($mediaField);
            $mediaData = $media['banner']['value'] ?? [];
        }

        return array_merge(
            ['type' => 'image'],
            $mediaData,
            [
                'target' => $data['target'] ?? '',
                'link' => $data['url'] ?? '',
                'attr1' => $data['attr1'] ?? '',
                'attr2' => $data['attr2'] ?? 0,
                'alt' => $data['alt'] ?? 0,
            ]
        );
    }
}
