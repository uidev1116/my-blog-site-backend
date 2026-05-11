<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\Facades\LocalStorage;
use ACMS_RAM;

class CategoryPageGenerator extends PageGenerator
{
    /**
     * @var int|null
     */
    protected $categoryId;

    /**
     * @param int $categoryId
     */
    public function setCategoryId(int $categoryId)
    {
        if ($categoryId < 1) {
            throw new \InvalidArgumentException('Invalid category id.');
        }
        $this->categoryId = $categoryId;
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (is_null($this->categoryId)) {
            throw new \RuntimeException('no selected category.');
        }
        if (is_null($this->maxPage)) {
            throw new \RuntimeException('no selected max page.');
        }
        if ($this->maxPage < 2) {
            throw new \RuntimeException('max page is less than 2.');
        }

        $pages = range(2, $this->maxPage);
        array_map(
            function (int $page) {
                $url = acmsLink([
                    'bid' => $this->targetBlogId,
                    'cid' => $this->categoryId,
                    'page' => $page,
                ]);
                $blogUrl = acmsLink(['bid' => $this->targetBlogId]);
                $categoryUrl = acmsLink(['bid' => $this->targetBlogId, 'cid' => $this->categoryId]);
                $categoryDir = substr($categoryUrl, strlen($blogUrl));
                $filepath = $categoryDir . 'page' . $page . '.html';

                if ($url) {
                    $this->addPage($url, $filepath);
                }
            },
            $pages,
        );

        if (is_null($this->categoryId)) {
            throw new \RuntimeException('no selected category.');
        }

        $bid = ACMS_RAM::categoryBlog($this->categoryId);
        $blogName = ACMS_RAM::blogName($bid);
        $this->logger->start(
            'カテゴリーの2ページ目以降を生成 【' . $blogName . ' > ' . ACMS_RAM::categoryName($this->categoryId) . '】',
            count($pages)
        );
        $this->handle();
    }

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    protected function writeContents(string $path, string $data): void
    {
        $destination = $this->destination->getDestinationPath() . $this->destination->getBlogCode();
        $destPath = $destination . $path;
        try {
            LocalStorage::makeDirectory(dirname($destPath));
            LocalStorage::put($destPath, $data);
        } catch (\Exception $e) {
            $this->logger->error('データの書き込みに失敗しました。', $destPath);
        }
    }
}
