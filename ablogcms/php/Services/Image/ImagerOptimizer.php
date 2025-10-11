<?php

namespace Acms\Services\Image;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use ImageOptimizer\OptimizerFactory;
use ImageOptimizer\Optimizer;

class ImagerOptimizer
{
    /**
     * @var Optimizer
     */
    private $optimizer;

    /**
     * コンストラクター
     */
    public function __construct()
    {
        $factory = new OptimizerFactory(['ignore_errors' => false]);
        $this->optimizer = $factory->get();
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
                    $this->optimizer->optimize($test);
                    $size = LocalStorage::getFileSize($test);
                    LocalStorage::remove($test);
                    if (empty($size)) {
                        return false;
                    }
                    return true;
                }
            }
        } catch (\Exception $e) {
            LocalStorage::remove($test);
        }
        return false;
    }
}
