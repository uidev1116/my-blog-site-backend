<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\Facades\LocalStorage;
use ACMS_RAM;

class CategoryArchivesGenerator extends PageGenerator
{
    /**
     * @var int|null
     */
    protected $categoryId;

    /**
     * @var string
     */
    protected $range;

    /**
     * @var int|null
     */
    protected $maxPage = 1;

    protected function getName(): string
    {
        if (is_null($this->categoryId)) {
            throw new \RuntimeException('no selected category.');
        }
        $bid = ACMS_RAM::categoryBlog($this->categoryId);
        $blogName = ACMS_RAM::blogName($bid);
        return 'カテゴリー毎のアーカイブ書き出し 【 ' . $blogName . '>' . ACMS_RAM::categoryName($this->categoryId) . '（' . $this->range .  '）】';
    }

    /**
     * @param int $categoryId
     * @return void
     */
    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    /**
     * @param string $range
     * @return void
     */
    public function setRange(string $range): void
    {
        $this->range = $range;
    }

    /**
     * @param int $maxPage
     * @return void
     */
    public function setMaxPage(int $maxPage): void
    {
        if ($maxPage < 1) {
            throw new \InvalidArgumentException('Invalid max page. Prease set more than 1.');
        }
        $this->maxPage = $maxPage;
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (is_null($this->maxPage)) {
            throw new \RuntimeException('no selected max page.');
        }
        if ($this->maxPage < 1) {
            throw new \RuntimeException('max page is less than 1.');
        }

        $pages = range(1, $this->maxPage);
        $this->logger->start($this->getName(), count($pages));

        foreach ($pages as $page) {
            $archiveContext = $this->getArchiveContext($this->range);
            $archivePageContext = array_merge($archiveContext, ['page' => $page]);
            $url = acmsLink($archivePageContext, false);

            $blogUrl = acmsLink(['bid' => $this->targetBlogId]);
            $archiveUrl = acmsLink($archiveContext);
            $dir = substr($archiveUrl, strlen($blogUrl));
            $filepath = $dir . $this->getFileName($page);
            if (!$url || !$filepath) {
                continue;
            }
            $this->addPage($url, $filepath);
        }
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

    /**
     * @param string $date
     * @return array{
     *  bid: int,
     *  date: string,
     *  cid?: int,
     * }
     */
    protected function getArchiveContext(string $date): array
    {
        $context = [
            'bid' => $this->targetBlogId,
            'date' => $date,
        ];
        if (!is_null($this->categoryId) && $this->categoryId > 0) {
            $context['cid'] = $this->categoryId;
        }
        return $context;
    }

    /**
     * @param int $page
     * @return string
     */
    protected function getFileName(int $page): string
    {
        if ($page > 1) {
            return  'page' . $page . '.html';
        }
        return 'index.html';
    }
}
