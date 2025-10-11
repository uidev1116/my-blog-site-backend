<?php

namespace Acms\Services\Template;

use Twig\Environment;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\Extension\DebugExtension;
use Acms\Services\Template\Twig\AcmsExtension;
use Acms\Services\Template\Twig\TokenParser\MarkdownTokenParser;
use Acms\Services\Template\Twig\CustomLoader;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use LogicException;

class Twig
{
    /**
     * @var \Twig\Environment
     */
    protected $twig;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $theme;

    /**
     * twigテンプレートをロード
     *
     * @param string $path
     * @param string $theme
     * @return void
     * @throws LogicException
     */
    public function load(string $path, string $theme): void
    {
        $this->path = $path;
        $this->theme = $theme;

        $templateDirectories = [];
        while (!empty($theme)) {
            $templateDirectories[] = THEMES_DIR . $theme;
            $theme  = preg_replace('/^[^@]*?(@|$)/', '', $theme);
        }
        $templateDirectories[] = THEMES_DIR . 'system';

        $loader = new CustomLoader($templateDirectories);
        $defaultFilters = array_keys((new Environment($loader))->getFilters());
        $this->twig = new Environment($loader, [
            'cache' => CACHE_DIR . 'twig',
            'debug' => isDebugMode(),
        ]);

        // ACMSカスタム拡張の登録
        $acmsExtension = new AcmsExtension();
        $acmsExtension->setDefaultFilters($defaultFilters);
        $this->addExtension($acmsExtension);
        $this->addExtension(new DebugExtension());

        // カスタムタグを追加
        $this->twig->addTokenParser(new MarkdownTokenParser());
    }

    /**
     * 拡張を追加
     *
     * @param AbstractExtension $extension
     * @return void
     * @throws LogicException
     */
    public function addExtension(AbstractExtension $extension): void
    {
        $this->twig->addExtension($extension);
    }

    /**
     * Functionを追加
     *
     * @param string $name
     * @param callable $function
     * @return void
     * @throws LogicException
     */
    public function addFunction(string $name, callable $function): void
    {
        $newFunction = new TwigFunction($name, $function);
        $this->twig->addFunction($newFunction);
    }

    /**
     * レンダリング
     *
     * @return string
     */
    public function render(): string
    {
        return $this->twig->render($this->path);
    }

    /**
     * Twigのキャッシュをクリア
     *
     * @return void
     */
    public function clearCache(): void
    {
        $cacheDir = CACHE_DIR . 'twig';
        if (!is_dir($cacheDir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }
    }
}
