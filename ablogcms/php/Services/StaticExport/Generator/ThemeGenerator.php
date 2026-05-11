<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Symfony\Component\Finder\Finder;

class ThemeGenerator extends Generator
{
    /**
     * @var string
     */
    protected $sourceTheme;

    /**
     * @var string[]
     */
    protected $exclusionList = [];

    /**
     * @param string $sourceTheme
     * @return void
     */
    public function setSourceTheme(string $sourceTheme): void
    {
        if ($sourceTheme === '') {
            throw new \InvalidArgumentException('source theme is empty.');
        }
        $this->sourceTheme = $sourceTheme;
    }

    protected function getName(): string
    {
        return 'テンプレートの書き出し ( ' . $this->sourceTheme . ' )';
    }

    /**
     * @param string[] $list
     * @return void
     */
    public function setExclusionList(array $list): void
    {
        $this->exclusionList = $list;
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (!$this->sourceTheme) {
            throw new \RuntimeException('no selected source theme.');
        }
        $finder = new Finder();
        $iterator = $finder
        ->in($this->sourceTheme)
        ->notPath('include')
        ->notPath('admin')
        ->notPath('ajax/field-values-group.json')
        ->name('/\.(html|htm|json)$/');

        if (config('forbid_direct_access_tpl') !== 'off') {
            $iterator->notPath(config('forbid_direct_access_tpl'));
            $iterator->notName(config('forbid_direct_access_tpl'));
        }
        $forbidPatterns = configArray('forbid_direct_access_file');
        if (count($forbidPatterns) > 0) {
            foreach ($forbidPatterns as $pattern) {
                // 正規表現パターンでチェック（/pattern/ 形式で指定）
                $iterator->notName($pattern);
            }
        }
        foreach ($this->exclusionList as $path) {
            if (!empty($path)) {
                $iterator->notPath($path);
            }
        }
        $iterator->files();

        $pages = $this->createPages($iterator);
        $this->logger->start($this->getName(), count($pages));

        $this->handle();
    }

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    protected function writeContents(string $path, string $data): void
    {
        $baseDir = $this->destination->getDestinationPath() . $this->destination->getBlogCode();
        $destPath = $baseDir . $path;
        try {
            LocalStorage::makeDirectory(dirname($destPath));
            LocalStorage::put($destPath, $data);
        } catch (\Exception $e) {
            $this->logger->error('データの書き込みに失敗しました。', $destPath);
        }
    }

    /**
     * @param \Throwable $th
     * @param string $url
     * @param int $statusCode
     * @return void
     */
    protected function handleError(\Throwable $th, string $url, int $statusCode): void
    {
        $this->logger->error($th->getMessage(), $url, $statusCode);
    }

    /**
     * @param \Symfony\Component\Finder\Finder $iterator
     * @return string[]
     */
    protected function createPages(\Symfony\Component\Finder\Finder $iterator): array
    {
        return array_map(
            function (\Symfony\Component\Finder\SplFileInfo $file) {
                $pathname = $file->getRelativePathname();
                $url = acmsLink(['bid' => $this->targetBlogId], false) . $pathname;
                if ($url) {
                    $this->addPage($url, $pathname);
                }
                return $url;
            },
            iterator_to_array($iterator, false)
        );
    }
}
