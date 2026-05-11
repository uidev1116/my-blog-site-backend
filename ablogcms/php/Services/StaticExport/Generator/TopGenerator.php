<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;

class TopGenerator extends Generator
{
    /**
     * @var string[]
     */
    protected $exclusionList = [];

    /**
     * @param string[] $list
     * @return void
     */
    public function setExclusionList($list): void
    {
        $this->exclusionList = $list;
    }

    protected function getName(): string
    {
        return "トップページの書き出し（{$this->targetBlogName}）";
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $pages = 0;
        $blogUrl = acmsLink(['bid' => $this->targetBlogId], false);
        if ($blogUrl) {
            $this->addPage($blogUrl, 'index.html');
            $pages++;
        } else {
            throw new \RuntimeException('blog url is empty.');
        }
        if (!in_array('rss2.xml', $this->exclusionList, true)) {
            $this->addPage($blogUrl . 'rss2.xml', 'rss2.xml');
            $pages++;
        }
        if (!in_array('sitemap.xml', $this->exclusionList, true)) {
            $this->addPage($blogUrl . 'sitemap.xml', 'sitemap.xml');
            $pages++;
        }
        $this->logger->start($this->getName(), $pages);
        $this->handle();
    }

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    protected function writeContents(string $path, string $data): void
    {
        $destPath = $this->destination->getDestinationPath() . $this->destination->getBlogCode() . $path;
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
}
