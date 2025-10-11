<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Async\await;

class CategoryGenerator extends Generator
{
    /**
     * @var int[]
     */
    protected $categoryIds = [];

    /**
     * @param int[] $categoryIds
     * @return void
     */
    public function setCategoryIds(array $categoryIds): void
    {
        $this->categoryIds = $categoryIds;
    }

    protected function getName(): string
    {
        return 'カテゴリートップの書き出し';
    }

    /**
     * @inheritDoc
     */
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve) {
                $pages = array_map(
                    function (int $categoryId) {
                        $blogUrl = acmsLink(['bid' => BID]);
                        $url = acmsLink([
                            'bid' => BID,
                            'cid' => $categoryId,
                        ]);
                        $categoryDir = substr($url, strlen($blogUrl));
                        $filepath = $categoryDir . 'index.html';
                        return new Page($url, $filepath);
                    },
                    $this->categoryIds
                );
                $this->logger->start($this->getName(), count($pages));
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

    /**
     * @param \Throwable $th
     * @param string $url
     * @return void
     */
    protected function handleError(\Throwable $th, string $url): void
    {
        if ($th instanceof \React\Http\Message\ResponseException) {
            $response = $th->getResponse();
            $this->logger->error(
                'データの取得に失敗しました。',
                $url,
                $response->getStatusCode()
            );
            return;
        }
        $this->logger->error($th->getMessage(), $url);
    }
}
