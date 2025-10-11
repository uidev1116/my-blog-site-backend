<?php

namespace Acms\Modules\Get\V2\Tag;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\TagHelper;
use Acms\Services\Facades\Database;

class Cloud extends Base
{
    /**
     * 階層の設定
     *
     * @inheritDoc
     */
    protected $axis = [
        'bid' => 'self',
        'cid' => 'self',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $vars = [
            'selectedTags' => [],
            'choiceTags' => [],
            'items' => [],
            'moduleFields' => null,
        ];
        $config = $this->loadModuleConfig();
        $tagHelper = new TagHelper($this->getBaseParams([]));
        $sql = $tagHelper->buildTagCloudQuery(
            $config->get('tag_cloud_order'),
            (int) $config->get('tag_cloud_threshold'),
            $this->limit ?? (int) $config->get('tag_cloud_limit'),
        );
        $q = $sql->get(dsn());
        $all = Database::query($q, 'all');
        if (empty($all)) {
            return $vars;
        }
        $vars['moduleFields'] = $this->buildModuleField();
        $vars['items'] = $tagHelper->getTagCloudTags(
            $this->bid,
            $this->cid,
            $all,
            $config->get('tag_cloud_url_context', ''),
            $config->get('tag_cloud_link_category_context') === 'on'
        );

        return $vars;
    }
}
