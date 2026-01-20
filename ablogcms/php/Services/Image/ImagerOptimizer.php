<?php

namespace Acms\Services\Image;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\OptimizerChain;

class ImagerOptimizer
{
    /**
     * @var OptimizerChain
     */
    private $optimizer;

    /**
     * コンストラクター
     */
    public function __construct()
    {
        $this->optimizer = OptimizerChainFactory::create();
    }

    /**
     * ロスレス圧縮を実行
     *
     * @param string $path
     * @return void
     */
    public function optimize(string $path): void
    {
        try {
            if ($this->optimizeTest($path)) {
                $this->optimizer->optimize($path);
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * ロスレス圧縮可能かテスト
     *
     * @param string $path
     * @return bool
     */
    public function optimizeTest(string $path): bool
    {
        $test = null;
        try {
            if (PublicStorage::isWritable($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $test = ARCHIVES_DIR . uniqueString() . '.' . $ext;
                if ($content = PublicStorage::get($path)) {
                    LocalStorage::put($test, $content);
                    $before = LocalStorage::getFileSize($test);
                    $this->optimizer->optimize($test);
                    $after = LocalStorage::getFileSize($test);
                    LocalStorage::remove($test);
                    if ($after >= $before) {
                        return false;
                    }
                    return true;
                }
            }
        } catch (\Exception $e) {
            if ($test) {
                LocalStorage::remove($test);
            }
        }
        return false;
    }
}
