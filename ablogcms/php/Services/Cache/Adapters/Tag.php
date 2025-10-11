<?php

namespace Acms\Services\Cache\Adapters;

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class Tag extends Standard
{
    /**
     * キャッシュを設定
     * $lifetimeを指定しない場合はデフォルト値を設定
     *
     * @param string $key
     * @param mixed $value
     * @param int $lifetime
     * @param array $tags
     * @return void
     */
    public function put(string $key, $value, int $lifetime = 0, $tags = []): void
    {
        $item = $this->adapter->getItem($key);
        $item->set($value);
        foreach ($tags as $tag) {
            $item->tag($tag);
        }
        $this->putItem($item, $lifetime);
    }

    /**
     * タグを指定してキャッシュ削除
     */
    public function invalidateTags($tags = [])
    {
        if ($this->adapter instanceof TagAwareAdapterInterface) {
            $this->adapter->invalidateTags($tags);
        }
    }
}
