<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Async\await;

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
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                if (is_null($this->categoryId)) {
                    $reject(new \RuntimeException('no selected category.'));
                    return;
                }
                if (is_null($this->maxPage)) {
                    $reject(new \RuntimeException('no selected max page.'));
                    return;
                }
                if ($this->maxPage < 2) {
                    $reject(new \RuntimeException('max page is less than 2.'));
                    return;
                }

                $pages = array_map(
                    function (int $page) {
                        $url = acmsLink([
                            'bid' => BID,
                            'cid' => $this->categoryId,
                            'page' => $page,
                        ]);
                        $blogUrl = acmsLink(['bid' => BID]);
                        $categoryUrl = acmsLink(['bid' => BID, 'cid' => $this->categoryId]);
                        $categoryDir = substr($categoryUrl, strlen($blogUrl));
                        $filepath = $categoryDir . 'page' . $page . '.html';
                        return new Page($url, $filepath);
                    },
                    range(2, $this->maxPage)
                );
                $this->logger->start(
                    'カテゴリーの2ページ目以降を生成 【' . \ACMS_RAM::categoryName($this->categoryId) . '（' . $this->categoryId . '）】',
                    count($pages)
                );
                await($this->handle($pages));
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
}
