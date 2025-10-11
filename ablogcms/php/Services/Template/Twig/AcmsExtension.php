<?php

namespace Acms\Services\Template\Twig;

use Acms\Services\Common\CorrectorFactory;
use Acms\Services\Template\Twig\FilterDecorator;
use ReflectionClass;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AcmsExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var array
     */
    protected $defaultFilters = [];

    /**
     * @param array $defaultFilters
     */
    public function setDefaultFilters(array $defaultFilters): void
    {
        $this->defaultFilters = $defaultFilters;
    }

    /**
     * グローバル変数を登録
     *
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $globalVarsList = globalVarsList();
        $globalVars = [];
        foreach ($globalVarsList as $key => $val) {
            $key = preg_replace('/^\%\{([^\}]+)\}/', '$1', $key);
            $globalVars[$key] = $val;
        }
        return $globalVars;
    }

    /**
     * 関数を登録
     *
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        $getModule = new GetModule();
        $touchModule = new TouchModule();
        $viteAsset = new ViteAsset();
        return [
            new TwigFunction('module', [$getModule, 'moduleFunction']),
            new TwigFunction('touch', [$touchModule, 'moduleFunction']),
            new TwigFunction('vite', [$viteAsset, 'viteFunction'], ['is_safe' => ['html']]),
            new TwigFunction('viteReactRefresh', [$viteAsset, 'viteReactRefreshFunction'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * フィルタ（校正オプション）を登録
     *
     * @return \Twig\TwigFilter[]
     */
    public function getFilters(): array
    {
        $factory = CorrectorFactory::singleton();
        $collection = $factory->getCollection();
        $filters = [];

        foreach ($collection as $corrector) {
            $reflectionClass = new ReflectionClass($corrector);
            $methods = $reflectionClass->getMethods();
            $decorator = new FilterDecorator($corrector);

            foreach ($methods as $method) {
                $name = $method->getName();
                $callback = function (...$args) use ($decorator, $name) {
                    return $decorator->$name(...$args);
                };
                if ($name === 'safe_html') {
                    $filters[] = new TwigFilter($name, $callback, ['is_safe' => ['html']]);
                } else {
                    // Twig標準のフィルターと衝突していたら acms_ プレフィックスをつけたフィルターのみ追加
                    if (!in_array($name, $this->defaultFilters, true)) {
                        $filters[] = new TwigFilter($name, $callback);
                    }
                    $filters[] = new TwigFilter("acms_{$name}", $callback);
                }
            }
        }
        return $filters;
    }
}
