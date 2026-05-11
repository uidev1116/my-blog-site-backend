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
            // ignore_errors を true にすることで、4xx・5xx などの HTTP エラーレスポンスでも
            // file_get_contents() が false ではなくレスポンスボディを返すようにする。
            // これにより、エラーレスポンスの JSON も正常に取得・表示できる。
            // また、file_get_contents() を利用することでhttp/https に加え file:// やローカルパスにも対応できる。
            $context = stream_context_create([
                'http' => ['ignore_errors' => true],
            ]);
            $contents = file_get_contents($uri, false, $context);
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
