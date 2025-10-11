<?php

use Acms\Modules\Get\Helpers\TopicPathHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Topicpath extends ACMS_GET
{
    public $_axis = [
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    public $_scope = [
        'uid' => 'global',
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
        'tag' => 'global',
        'field' => 'global',
        'date' => 'global',
        'start' => 'global',
        'end' => 'global',
        'page' => 'global',
    ];

    /**
     * @var boolean
     */
    protected $firstItem = true;

    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $topicPathHelper = new TopicPathHelper($this->getBaseParams([]));

        TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);
        $loop = 1;

        //------
        // blog
        if ('0' !== strval(config('mo_topicpath_blog_limit'))) {
            $orderPosition = config('mo_topicpath_blog_base') === 'top' ? 'top' : 'bottom';
            $order = config('mo_topicpath_blog_order') === 'asc' ? 'asc' : 'desc';
            $blogQuery = $topicPathHelper->buildBlogQuery(
                $this->bid,
                $this->blogAxis(),
                $orderPosition,
                (int) config('mo_topicpath_blog_limit')
            );
            $blogs = Database::query($blogQuery->get(dsn()), 'all');
            $blogData = $topicPathHelper->getBlogList(
                $blogs,
                $orderPosition,
                $order,
                config('mo_topicpath_root_label'),
                config('mo_topicpath_blog_field') === 'on',
                $loop
            );
            $this->buildTemplate($blogData, 'blog:loop', 'blogField', $tpl);
        }

        //----------
        // category
        if ($this->cid && strval(config('mo_topicpath_category_limit')) !== '0') {
            $orderPosition = config('mo_topicpath_category_base') === 'top' ? 'top' : 'bottom';
            $order = config('mo_topicpath_category_order') === 'asc' ? 'asc' : 'desc';
            $categoryQuery = $topicPathHelper->buildCategoryQuery(
                $this->cid,
                $this->categoryAxis(),
                $orderPosition,
                (int) config('mo_topicpath_category_limit')
            );
            $coategories = Database::query($categoryQuery->get(dsn()), 'all');
            $categoryData = $topicPathHelper->getCategoryList(
                $coategories,
                $orderPosition,
                $order,
                $this->bid,
                config('mo_topicpath_category_field') === 'on',
                $loop
            );
            $this->buildTemplate($categoryData, 'category:loop', 'categoryField', $tpl);
        }

        //-------
        // entry
        if ($this->eid && config('mo_topicpath_entry') === 'on') {
            $entryQuery = $topicPathHelper->buildEntryQuery($this->eid);
            $entry = Database::query($entryQuery->get(dsn()), 'row');
            $entryData = $topicPathHelper->getEntry(
                $entry,
                'on' === config('mo_topicpath_ignore_ecdempty'),
                $this->bid,
                config('mo_topicpath_entry_field') === 'on',
                $loop
            );
            if ($entryData) {
                $this->buildTemplate([$entryData], 'entry', 'entryField', $tpl);
            }
        }
        return $tpl->get();
    }

    /**
     * テンプレートを組み立てる
     *
     * @param array $data
     * @param string $blockName
     * @param string $fieldName
     * @param Template $tpl
     * @return void
     */
    protected function buildTemplate(array $data, string $blockName, string $fieldName, Template $tpl)
    {
        foreach ($data as $i => $row) {
            if (!$this->firstItem) {
                $tpl->add(['glue', $blockName]);
            } else {
                $this->firstItem = false;
            }
            if (isset($row['field']) && $row['field'] instanceof Field) {
                $tpl->add([$fieldName, $blockName], TemplateHelper::buildField($row['field'], $tpl));
            }
            $tpl->add($blockName, [
                'name' => $row['name'],
                'title' => $row['name'],
                'url' => $row['url'],
                'sNum' => $row['sNum'],
            ]);
        }
    }
}
