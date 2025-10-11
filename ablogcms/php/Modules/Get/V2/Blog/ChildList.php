<?php

namespace Acms\Modules\Get\V2\Blog;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Blog\BlogHelper;
use Acms\Services\Facades\Database;
use SQL_Select;
use ACMS_RAM;

class ChildList extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * スコープの設定
     *
     * @inheritDoc
     */
    protected $scopes = [
        'bid' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Blog\BlogHelper
     */
    protected $blogHelper;

    /**
     * @return array{
     *  order: string,
     *  limit: int,
     *  geoLocation: bool,
     * }
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            'order' => $config->get('blog_child_list_order'),
            'limit' => $this->limit ?? (int) $config->get('blog_child_list_limit'),
            'geoLocation' => $config->get('blog_child_list_geolocation_on') === 'on',
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        if (!$this->setConfigTrait()) {
            return [];
        }
        $vars = [];
        $this->boot();
        $sql = $this->buildQuery();
        $blogs = $this->getBlogs($sql);
        $vars = $this->preBuild($vars, $blogs);
        $vars['items'] = $this->getBlogData($blogs);

        $currentBlog = loadBlog($this->bid);
        $currentBlogData = [
            'bid' => $this->bid,
            'code' => $currentBlog->get('code'),
            'name' => $currentBlog->get('name'),
            'domain' => $currentBlog->get('domain'),
            'indexing' => $currentBlog->get('indexing'),
            'status' => $currentBlog->get('status'),
            'createdAt' => $currentBlog->get('generated_datetime'),
        ];
        $currentBlogData['url'] = acmsLink([
            'bid' => $this->bid,
        ]);
        $currentBlogData['fields'] = $this->buildFieldTrait(loadBlogField($this->bid));
        $vars['currentBlog'] = $currentBlogData;
        $vars['moduleFields'] = $this->buildModuleField();

        return $vars;
    }

    /**
     * 起動処理
     *
     * @return void
     */
    protected function boot(): void
    {
        $this->blogHelper = new BlogHelper($this->getBaseParams([]));
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
    protected function getBlogs(SQL_Select $sql): array
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
     * @param array $vars
     * @param array $blogs
     * @return array
     */
    protected function preBuild(array $vars, array $blogs): array
    {
        return $vars;
    }

    /**
     * ブログデータの組み立て
     *
     * @param array $blogs
     * @return array
     */
    protected function getBlogData(array $blogs): array
    {
        $data = [];
        foreach ($blogs as $row) {
            $bid = intval($row['blog_id']);
            $vars = [
                'bid' => $bid,
                'code' => $row['blog_code'] ?? null,
                'name' => $row['blog_name'] ?? null,
                'domain' => $row['blog_domain'] ?? null,
                'indexing' => $row['blog_indexing'] ?? null,
                'status' => $row['blog_status'] ?? null,
                'createdAt' => $row['blog_generated_datetime'] ?? null,
            ];
            $vars['url'] = acmsLink([
                'bid' => $bid,
            ], false);
            $vars['fields'] = $this->buildFieldTrait(loadBlogField($bid));
            $vars['geo'] = [
                'lat' => $row['latitude'] ?? null,
                'lng' => $row['longitude'] ?? null,
                'distance' => $row['distance'] ?? null,
            ];
            $data[] = $vars;
        }
        return $data;
    }
}
