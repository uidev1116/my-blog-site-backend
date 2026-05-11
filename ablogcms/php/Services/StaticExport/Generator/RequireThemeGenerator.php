<?php

namespace Acms\Services\StaticExport\Generator;

use Symfony\Component\Finder\Finder;

class RequireThemeGenerator extends ThemeGenerator
{
    /**
     * @var array
     */
    protected $includeList = [];

    protected function getName(): string
    {
        return '必須テンプレートの書き出し ( ' . $this->sourceTheme . ' )';
    }

    /**
     * @param string[] $list
     */
    public function setIncludeList(array $list): void
    {
        $this->includeList = $list;
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (!$this->sourceTheme) {
            throw new \RuntimeException('no selected source theme.');
        }
        $includeList = [];
        foreach ($this->includeList as $path) {
            if (!empty($path)) {
                $includeList[] = $path;
            }
        }

        if (count($includeList) === 0) {
            return;
        }

        $finder = new Finder();
        $iterator = $finder->in($this->sourceTheme);
        foreach ($includeList as $path) {
            $iterator->path($path);
        }
        $iterator->files();

        $pages = $this->createPages($iterator);
        $this->logger->start($this->getName(), count($pages));

        $this->handle();
    }
}
