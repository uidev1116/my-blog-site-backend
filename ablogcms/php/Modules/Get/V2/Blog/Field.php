<?php

namespace Acms\Modules\Get\V2\Blog;

use Acms\Modules\Get\V2\Base;
use ACMS_RAM;
use Exception;
use RuntimeException;

class Field extends Base
{
    /**
     * スコープの設定
     * @var array{
     *     uid?: 'local' | 'global',
     *     cid?: 'local' | 'global',
     *     eid?: 'local' | 'global',
     *     keyword?: 'local' | 'global',
     *     tag?: 'local' | 'global',
     *     field?: 'local' | 'global',
     *     date?: 'local' | 'global',
     *     start?: 'local' | 'global',
     *     end?: 'local' | 'global',
     *     page?: 'local' | 'global',
     *     order?: 'local' | 'global'
     * }
     */
    protected $scopes = [
        'bid' => 'global',
    ];

    /**
     * @return array|never
     */
    public function get(): array
    {
        try {
            if (!$this->bid) {
                throw new RuntimeException('Not found blog id.');
            }
            $blog = ACMS_RAM::blog($this->bid);
            if (empty($blog)) {
                throw new RuntimeException('Not found blog.');
            }
            $status = ACMS_RAM::blogStatus($this->bid);
            if (!sessionWithAdministration() && 'close' === $status) {
                throw new RuntimeException('Permission denied.');
            }
            if (!sessionWithSubscription() && 'secret' === $status) {
                throw new RuntimeException('Permission denied.');
            }
            $vars = [
                'bid' => (int) $blog['blog_id'],
                'code' => $blog['blog_code'],
                'status' => $blog['blog_status'],
                'name' => $blog['blog_name'],
                'domain' => $blog['blog_domain'],
                'indexing' => $blog['blog_indexing'],
                'createdAt' => $blog['blog_generated_datetime'],
            ];
            $vars['fields'] = $this->buildFieldTrait(loadBlogField($this->bid));
            $vars['moduleFields'] = $this->buildModuleField();
            $geo = loadGeometry('bid', $this->bid);
            $vars['geo'] = $this->buildFieldTrait($geo);

            return $vars;
        } catch (Exception $e) {
            return [];
        }
    }
}
