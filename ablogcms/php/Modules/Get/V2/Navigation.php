<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Services\Facades\Media;
use Field;

class Navigation extends Base
{
    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();
        $labels = $config->getArray('navigation_label');
        $mediaIds = array_map(function ($media) {
            return (int) $media;
        }, $config->getArray('navigation_media', true));
        $mediaList = Media::mediaEagerLoad($mediaIds);

        $data = [];
        foreach ($labels as $i => $label) {
            $id = $i + 1;
            // 公開チェックが外れている場合はスキップ
            if ($config->get('navigation_publish', 'off', $i) !== 'on') {
                continue;
            }
            $pid = (int) $config->get('navigation_parent', 0, $i);
            $mediaData = null;
            if ($mediaId = (int) $config->get('navigation_media', null, $i)) {
                $mediaField = new Field();
                $key = 'media';
                $mediaField->set("{$key}@media", $mediaId);
                Media::injectMediaField($mediaField, $mediaList, [$key]);
                $media = $this->buildFieldTrait($mediaField);
                $mediaData = $media[$key]['value'] ?? null;
            }
            $data[] = [
                'id' => $id,
                'parent' => $pid,
                'label' => setGlobalVars($label),
                'url' => setGlobalVars($config->get('navigation_uri', null, $i)),
                'target' => $config->get('navigation_target', null, $i),
                'attr' => $config->get('navigation_attr', null, $i),
                'attr2' => $config->get('navigation_a_attr', null, $i),
                'media' => $mediaData,
            ];
        }
        $items = $this->buildTree($data, 0, 1);

        try {
            return [
                'items' => $items,
                'moduleFields' => $this->buildModuleField(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * ツリーを構築する
     *
     * @param array $items
     * @param integer $parentId
     * @return array
     */
    protected function buildTree(array $items, $parentId = 0, int $depth = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            $currentParentId = (int) $item['parent'];
            if ($currentParentId === $parentId) {
                $item['depth'] = $depth;
                if ($children = $this->buildTree($items, (int) $item['id'], $depth + 1)) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
