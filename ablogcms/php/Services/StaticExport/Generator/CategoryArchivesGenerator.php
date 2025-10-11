<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Async\await;

class CategoryArchivesGenerator extends PageGenerator
{
    /**
     * @var int|null
     */
    protected $categoryId;

    /**
     * @var array
     */
    protected $monthRange = [];

    /**
     * @var int|null
     */
    protected $maxPage = 1;

    protected function getName(): string
    {
        return 'カテゴリー毎のアーカイブ書き出し 【 ' . \ACMS_RAM::categoryName($this->categoryId) . '（' . $this->categoryId .  '）】';
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
     * @param string[] $monthRange
     * @return void
     */
    public function setMonthRange(array $monthRange): void
    {
        $this->monthRange = $monthRange;
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
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                if (is_null($this->maxPage)) {
                    $reject(new \RuntimeException('no selected max page.'));
                    return;
                }
                if ($this->maxPage < 1) {
                    $reject(new \RuntimeException('max page is less than 1.'));
                    return;
                }

                $pagesPerMonth = [];
                foreach ($this->monthRange as $ym) {
                    $pages = [];
                    foreach (range(1, $this->maxPage) as $page) {
                        $archiveContext = $this->getArchiveContext($ym);
                        $archivePageContext = array_merge($archiveContext, ['page' => $page]);
                        $url = acmsLink($archivePageContext, false);

                        $blogUrl = acmsLink(['bid' => BID]);
                        $archiveUrl = acmsLink($archiveContext);
                        $dir = substr($archiveUrl, strlen($blogUrl));
                        $filepath = $dir . $this->getFileName($page);
                        $pages[] = new Page($url, $filepath);
                    }
                    $pagesPerMonth[] = $pages;
                }

                $this->logger->start($this->getName(), count(array_flatten($pagesPerMonth)));
                foreach ($pagesPerMonth as $pages) {
                    await($this->handle($pages));
                }
                $resolve(null);
            }
        );
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
            'bid' => BID,
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
