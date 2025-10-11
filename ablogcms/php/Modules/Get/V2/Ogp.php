<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\OgpHelper;
use Acms\Services\Facades\Common;

class Ogp extends Base
{
    /**
     * スコープ設定
     *
     * @inheritDoc
     */
    protected $scopes = [
        'uid' => 'global',
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
        'tag' => 'global',
        'date' => 'global',
        'page' => 'global',
    ];

    /**
     * 連結文字列
     *
     * @var string
     */
    protected $glue = ' | ';

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();
        $ogpHelper = new OgpHelper(['config' => $config]);
        $ogpHelper->setGlue($config->get('ogp_title_delimiter', ' | '));
        $vars = [
            'title' => $ogpHelper->getTitle(),
            'description' => $ogpHelper->getDescription(),
            'keywords' => $ogpHelper->getKeywords(),
            'type' => $ogpHelper->getType(),
        ];

        $imageData = $ogpHelper->getImage();
        if ($imageData) {
            $vars = array_merge($vars, [
                'image' => $imageData['type'] === 'media' ? Common::toAbsoluteUrl($imageData['path'], MEDIA_LIBRARY_DIR, true) : Common::toAbsoluteUrl($imageData['path'], ARCHIVES_DIR, true),
                'image@x' => $imageData['width'],
                'image@y' => $imageData['height'],
                'image@type' => $imageData['type'],
            ]);
        }

        return $vars;
    }
}
