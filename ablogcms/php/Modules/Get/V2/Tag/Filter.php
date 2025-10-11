<?php

namespace Acms\Modules\Get\V2\Tag;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\TagHelper;
use Acms\Services\Facades\Database;

class Filter extends Base
{
    /**
     * スコープ設定
     *
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'tag' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $vars = [
            'selectedTags' => [],
            'choiceTags' => [],
            'moduleFields' => [],
        ];
        $cnt = count($this->tags);
        if ($cnt === 0) {
            return $vars;
        }
        $vars['moduleFields'] = $this->buildModuleField();
        $tagHelper = new TagHelper($this->getBaseParams([]));
        $config = $this->loadModuleConfig(); // コンフィグをロード
        $selectedLimit = (int) $config->get('tag_filter_selected_limit');

        // タグフィルターのSQLを生成
        $sql = $tagHelper->buildTagFilterQuery(
            $this->tags,
            $config->get('tag_filter_order'),
            (int) $config->get('tag_filter_threshold'),
            (int) $config->get('tag_filter_limit'),
        );
        $q = $sql->get(dsn());
        $all = Database::query($q, 'all');

        // 選択中のタグを取得
        $vars['selectedTags'] = $tagHelper->getSelectedTags(
            $this->bid,
            $this->cid,
            $this->tags,
            $config->get('tag_filter_url_context'),
            $config->get('tag_filter_link_category_context') === 'on',
            (int) $config->get('tag_filter_selected_limit')
        );
        if ($selectedLimit > $cnt && count($all) > 0) {
            // 選択可能なタグを取得
            $vars['choiceTags'] = $tagHelper->getChoiceTags($all);
        }
        return $vars;
    }
}
