<?php

use Acms\Modules\Get\Helpers\TagHelper;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Database;

class ACMS_GET_Tag_Filter extends ACMS_GET_Tag_Cloud
{
    public $_scope = [
        'tag' => 'global',
    ];

    public function get()
    {
        $cnt = count($this->tags);
        if ($cnt === 0) {
            return '';
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $tagHelper = new TagHelper($this->getBaseParams([]));
        $selectedLimit = (int) config('tag_filter_selected_limit');

        TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);

        $sql = $tagHelper->buildTagFilterQuery(
            $this->tags,
            config('tag_filter_order'),
            (int) config('tag_filter_threshold'),
            (int) config('tag_filter_limit'),
        );
        $q = $sql->get(dsn());
        $all = Database::query($q, 'all');

        $selectedTags = $tagHelper->getSelectedTags(
            $this->bid,
            $this->cid,
            $this->tags,
            config('tag_filter_url_context'),
            config('tag_filter_link_category_context') === 'on',
            (int) config('tag_filter_selected_limit')
        );
        foreach ($selectedTags as $tag) {
            if (next($selectedTags)) {
                $tpl->add(['glue', 'selected:loop']);
            }
            $tpl->add('selected:loop', $tag);
        }
        if ($selectedLimit > $cnt && count($all) > 0) {
            $choiceTags = $tagHelper->getChoiceTags($all);

            foreach ($choiceTags as $tag) {
                if (next($choiceTags)) {
                    $tpl->add(['glue', 'choice:loop']);
                }
                $tpl->add('choice:loop', $tag);
            }
        }

        return $tpl->get();
    }
}
