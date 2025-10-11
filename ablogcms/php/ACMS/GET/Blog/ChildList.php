<?php

use Acms\Modules\Get\Helpers\Blog\BlogHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Blog_ChildList extends ACMS_GET
{
    use \Acms\Traits\Modules\ConfigTrait;

    public $_scope = [
        'bid'   => 'global',
    ];

    /**
     * @var array
     */
    protected $blog = [];

    /**
     * @var \Acms\Modules\Get\Helpers\Blog\BlogHelper
     */
    protected $blogHelper;

    /**
     * @return array{
     *  order: string,
     *  limit: int,
     *  parent_loop_class: string,
     *  loop_class: string,
     *  geoLocation: bool,
     * }
     */
    protected function initConfig(): array
    {
        return [
            'order' => config('blog_child_list_order'),
            'limit' => intval(config('blog_child_list_limit')),
            'parent_loop_class' => config('blog_child_list_parent_loop_class'),
            'loop_class' => config('blog_child_list_loop_class'),
            'geoLocation' => config('blog_child_list_geolocation_on') === 'on',
        ];
    }

    /**
     * @inheritDoc
     */
    public function get()
    {
        if (!$this->setConfigTrait()) {
            return '';
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        TemplateHelper:: buildModuleField($tpl);

        $this->boot();
        $sql = $this->buildQuery();
        $this->blog = $this->getblogs($sql);
        [$isRunnable, $renderTpl] = $this->preBuild($tpl);
        if (!$isRunnable) {
            if ($renderTpl) {
                return $tpl->get();
            } else {
                return '';
            }
        }
        $this->build($tpl);
        if ($this->bid) {
            $currentBlog = loadBlogField($this->bid);
            $currentBlog->overload(loadBlog($this->bid));
            $currentBlog->set('url', acmsLink([
                'bid'   => $this->bid,
            ]));
            $tpl->add('currentBlog', TemplateHelper::buildField($currentBlog, $tpl));
        }
        $rootVars = $this->getRootVars();
        $tpl->add(null, $rootVars);
        return $tpl->get();
    }

    /**
     * 起動処理
     *
     * @return void
     */
    protected function boot(): void
    {
        $this->blogHelper = new BlogHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * クエリの組み立て
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        return $this->blogHelper->buildBlogListQuery($this->bid, $this->keyword, $this->Field, $this->config['order'], (int) $this->config['limit'], $this->config['geoLocation']);
    }

    /**
     * ブログデータの取得
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function getblogs(SQL_Select $sql): array
    {
        $q = $sql->get(dsn());
        $blogs = Database::query($q, 'all');
        foreach ($blogs as $blog) {
            ACMS_RAM::blog($blog['blog_id'], $blog);
        }
        return $blogs;
    }

    /**
     * ビルド前のカスタム処理
     *
     * @param Template $tpl
     * @return array{0: bool, 1: bool} 0: 処理を続けるかどうか, 1: ここまでの処理をレンダリングするかどうか
     */
    protected function preBuild(Template $tpl): array
    {
        return [true, true];
    }

    /**
     * テンプレートの組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function build($tpl): void
    {
        $loopClass = $this->config['loop_class'];

        $j = count($this->blog) - 1;
        foreach ($this->blog as $i => $row) {
            $bid = intval($row['blog_id']);

            //-------
            // field
            $Field  = loadBlogField($bid);
            foreach ($row as $key => $val) {
                if ($key !== 'geo_geometry') {
                    $Field->setField(preg_replace('/blog\_/', '', $key), $val);
                }
            }
            $Field->set('url', acmsLink([
                'bid'   => $bid,
            ]));
            $Field->set('blog:loop.class', $loopClass);

            //------
            // glue
            if ($i !== $j) {
                $tpl->add('glue');
            }
            $vars = TemplateHelper::buildField($Field, $tpl);
            if (isset($row['distance'])) {
                $vars['geo_distance'] = $row['distance'];
            }
            if (isset($row['latitude'])) {
                $vars['geo_lat'] = $row['latitude'];
            }
            if (isset($row['longitude'])) {
                $vars['geo_lng'] = $row['longitude'];
            }
            $tpl->add('blog:loop', $vars);
        }
    }

    /**
     * ルート変数を取得
     *
     * @return array
     */
    public function getRootVars(): array
    {
        return [
            'parent.loop.class' => $this->config['parent_loop_class'] ?? '',
        ];
    }
}
