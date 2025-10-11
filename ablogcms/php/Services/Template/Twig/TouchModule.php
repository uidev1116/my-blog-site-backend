<?php

namespace Acms\Services\Template\Twig;

use ACMS_Namespace;
use ACMS_GET;
use Exception;
use RuntimeException;
use Timer;

class TouchModule
{
    /**
     * twigテンプレートから「module」関数で呼び出し
     *
     * @param string $name
     * @return bool
     * @throws RuntimeException
     * @throws Exception
     */
    public function moduleFunction(string $name): bool
    {
        $timer1 = null;
        $timer2 = null;

        // timer start
        if (isBenchMarkMode()) {
            global $query_result_count;
            $sql_count = $query_result_count;
            $timer1 = new Timer();
            $timer1->start();
        }

        $namespace = ACMS_Namespace::singleton();
        $moduleClass = $namespace->getModuleClass('GET', $name);

        if (empty($moduleClass)) {
            return false;
        }
        // timer start
        if (isBenchMarkMode()) {
            $timer2 = new Timer();
            $timer2->start();
        }

        /**
         * タッチモジュール実行
         * @var ACMS_GET $touchModule
         */
        $touchModule = new $moduleClass('touch', '/', [], [], null);
        $res = $touchModule->fire();

        // timer end
        if (isBenchMarkMode()) {
            global $bench;
            global $bench_boot;
            $timer2->end();
            $timer1->end();
            $bench_boot += $timer1->time;
            $bench['module'][] = [
                'module' => $name,
                'identifier' => '',
                'sql_count' => ($query_result_count - $sql_count),
                'run_time' => $timer2->time,
                'sort_key' => $timer2->time,
            ];
        }
        return !!$res;
    }
}
