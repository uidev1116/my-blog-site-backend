<?php

use Acms\Modules\Get\Helpers\TagHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Tag_Cloud extends ACMS_GET
{
    public $_axis  = [
        'bid' => 'self',
        'cid' => 'self'
    ];

    public function get()
    {
        $tagHelper = new TagHelper($this->getBaseParams([]));
        $sql = $tagHelper->buildTagCloudQuery(
            config('tag_cloud_order'),
            (int) config('tag_cloud_threshold'),
            (int) config('tag_cloud_limit'),
        );
        $q = $sql->get(dsn());
        $all = Database::query($q, 'all');
        if (empty($all)) {
            return '';
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);

        $tags = $tagHelper->getTagCloudTags($this->bid, $this->cid, $all, config('tag_cloud_url_context', false), config('tag_cloud_link_category_context') === 'on');

        foreach ($tags as $tag) {
            if (next($tags)) {
                $tpl->add(['glue', 'tag:loop']);
            }
                $tpl->add('tag:loop', $tag);
        }
        return $tpl->get();
    }
}
