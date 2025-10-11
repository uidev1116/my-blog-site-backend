<?php

class ACMS_GET_Feed_ExList extends ACMS_GET
{
    /**
     * @var string
     */
    protected $source;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @var int
     */
    protected $newtime;

    /**
     * @var int
     */
    protected $feed_exlist_cache_expire;

    /**
     * @var string
     */
    protected $mo_feed_exlist_notfound;

    /**
     * @var 'rss20' | 'atom10'
     */
    protected $kind;

    public function get()
    {
        $this->source           = config('feed_exlist_source');
        $this->limit            = intval(config('feed_exlist_limit'));
        $this->offset           = intval(config('feed_exlist_offset'));
        $this->newtime          = intval(config('feed_exlist_newtime'));
        $this->feed_exlist_cache_expire = intval(config('feed_exlist_cache_expire'));
        $this->mo_feed_exlist_notfound  = config('mo_feed_exlist_notfound');
        $this->kind             = config('feed_exlist_kind');

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);
        $feeds = [];

        //----------
        // cache
        $id = md5($this->source);
        $cache = Cache::module();
        $cacheItem = $cache->getItem($id);
        if ($cacheItem && $cacheItem->isHit()) {
            $feeds = acmsDangerUnserialize((string) $cacheItem->get());
        }
        if (empty($feeds)) {
            $RSS   = new FeedParser($this->source, $this->kind);
            $feeds = $RSS->get();
            $cache->forget($id);
            if (!empty($this->feed_exlist_cache_expire)) {
                $cacheItem->set(acmsSerialize($feeds));
                $cache->putItem($cacheItem, $this->feed_exlist_cache_expire);
            }
        }

        //----------
        // notFound
        if (empty($feeds['items'])) {
            if ($this->mo_feed_exlist_notfound == 'on') {
                $Tpl->add('notFound');
            }
            return $Tpl->get();
        }

        //----------
        // limit
        $limit  = count($feeds['items']) < $this->limit ? count($feeds['items']) : $this->limit;

        //----------
        // slice
        foreach (array_slice($feeds['items'], $this->offset, $limit) as $row) {
            if (requestTime() <= @strtotime($row['datetime']) + $this->newtime) {
                $Tpl->add('new');
            }
            $row += $this->buildDate(@$row['datetime'], $Tpl, 'item:loop');
            $Tpl->add('item:loop', $this->array_split($row));
        }

        $Tpl->add(null, $this->array_split($feeds['meta']));

        return $Tpl->get();
    }

    public function array_split($array)
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $_key => $_val) {
                    $array[$key . '_' . $_key] = $_val;
                }
                unset($array[$key]);
            }
        }
        return $array;
    }
}
