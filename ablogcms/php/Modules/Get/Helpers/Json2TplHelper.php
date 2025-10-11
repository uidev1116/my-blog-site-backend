<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Services\Facades\Cache;

class Json2TplHelper
{
    /**
     * 添え字が0から連続する数値(=配列とみなせる)ときにtrue
     *
     * @param array $ary
     * @return boolean
     */
    public function isVector($ary)
    {
        return array_values($ary) === $ary;
    }

    /**
     * urlからコンテンツの取得
     *
     * @param string $uri
     *
     * @return string
     */
    public function getContents($uri)
    {
        try {
            $contents = file_get_contents($uri);
            if ($contents === false) {
                throw new \RuntimeException('Failed to get contents.');
            }
            if ($contents === '') {
                throw new \RuntimeException('Empty contents.');
            }
            $charset = mb_detect_encoding($contents, 'UTF-8, EUC-JP, SJIS-win, SJIS');
            if ($charset && 'UTF-8' !== $charset) {
                $contents = mb_convert_encoding($contents, 'UTF-8', $charset);
            }
            if ($contents === false) {
                throw new \RuntimeException('Failed to convert encoding.');
            }
            return $contents;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * キャッシュの取得
     *
     * @param string $uri
     *
     * @return string|bool
     */
    public function getJsonCache($uri)
    {
        $id = $this->getCacheId($uri);
        $cache = Cache::module();
        $cacheItem = $cache->getItem($id);
        if ($cacheItem->isHit()) {
            return (string) $cacheItem->get();
        }
        return false;
    }

    /**
     * キャッシュの保存
     *
     * @param string $uri
     * @param string $contents
     * @param int $expire
     */
    public function saveCache(string $uri, string $contents, int $expire = 0)
    {
        $id = $this->getCacheId($uri);
        $cache = Cache::module();
        $cache->put($id, $contents, $expire);
    }

    /**
     * キャッシュidの取得
     *
     * @param string $uri
     * @return string
     */
    private function getCacheId($uri)
    {
        return md5($uri);
    }
}
