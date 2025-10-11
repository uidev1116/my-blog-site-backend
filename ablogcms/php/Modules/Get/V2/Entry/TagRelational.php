<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\V2\Entry\Summary;
use Acms\Modules\Get\Helpers\Entry\TagRelationalHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use SQL_Select;

class TagRelational extends Summary
{
    /**
     * @inheritDoc
     */
    protected $scopes = [
        'eid' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\TagRelationalHelper
     */
    protected $tagRelationalHelper;

    /**
     * @inheritDoc
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            'order' => $this->order ? $this->order : $config->get('entry_tag-relational_order'),
            'limit' => $this->limit ?? (int) $config->get('entry_tag-relational_limit'),
            'offset' => 0,
            'displayIndexingOnly' => $config->get('entry_tag-relational_indexing') === 'on',
            'displayMembersOnly' => $config->get('entry_tag-relational_members_only') === 'on',
            'displaySecretEntry' => $config->get('entry_tag-relational_secret') === 'on',
            'notfoundStatus404' => $config->get('entry_tag-relational_notfound_status_404') === 'on',
            'fulltextEnabled' => true,
            'fulltextWidth' => (int) $config->get('entry_tag-relational_fulltext_width'),
            'fulltextMarker' => $config->get('entry_tag-relational_fulltext_marker'),
            // 画像系
            'includeMainImage' => true,
            'mainImageTarget' => config('entry_tag-relational_main_image_target', 'field'),
            'mainImageFieldName' => config('entry_tag-relational_main_image_field_name'),
            'displayNoImageEntry' => $config->get('entry_tag-relational_noimage') === 'on',
            // フィールド・情報
            'includeEntryFields' => $config->get('entry_tag-relational_entry_field') === 'on',
            'includeCategory' => $config->get('entry_tag-relational_category_on') === 'on',
            'includeCategoryFields' => $config->get('entry_tag-relational_category_field_on') === 'on',
            'includeUser' => $config->get('entry_tag-relational_user_on') === 'on',
            'includeUserFields' => $config->get('entry_tag-relational_user_field_on') === 'on',
            'includeBlog' => $config->get('entry_tag-relational_blog_on') === 'on',
            'includeBlogFields' => $config->get('entry_tag-relational_blog_field_on') === 'on',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function boot(): void
    {
        $this->entryHelper = new EntryHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
        $this->tagRelationalHelper = new TagRelationalHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * @inheritDoc
     */
    protected function buildQuery(): SQL_Select
    {
        return $this->tagRelationalHelper->buildQuery();
    }

    /**
     * @inheritDoc
     */
    protected function buildPagination(): ?array
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    protected function fixVars(array $vars): array
    {
        unset($vars['pager']);
        unset($vars['pagination']);
        return $vars;
    }
}
