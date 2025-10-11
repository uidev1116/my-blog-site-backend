<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Async\await;

class PageGenerator extends Generator
{
    /**
     * @var bool
     */
    protected $shouldGenerateNextPage = true;

    /**
     * @var int|null
     */
    protected $maxPage;

    /**
     * @param int $maxPage
     * @return void
     */
    public function setMaxPage(int $maxPage): void
    {
        if ($maxPage < 2) {
            throw new \InvalidArgumentException('Invalid max page. Prease set more than 2.');
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
                if ($this->maxPage < 2) {
                    $reject(new \RuntimeException('max page is less than 2.'));
                    return;
                }

                $pages = array_map(
                    function (int $page) {
                        $url = acmsLink([
                            'bid' => BID,
                            'page' => $page,
                        ]);
                        $filename = 'page' . $page . '.html';
                        return new Page($url, $filename);
                    },
                    range(2, $this->maxPage)
                );

                $this->logger->start('2ページ以降を生成', count($pages));
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
            LocalStorage::makeDirectory($destination);
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
        // ページネーションの生成は1度404が返ってきたら、それ以降のページは404が返ってくるため、次のページの生成を中止する
        $this->stopGenerateNextPage();
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

    /**
     * 次のページを生成するかどうか
     * @return bool
     */
    protected function shouldGenerateNextPage(): bool
    {
        return $this->shouldGenerateNextPage;
    }

    /**
     * @return void
     */
    private function stopGenerateNextPage(): void
    {
        $this->shouldGenerateNextPage = false;
    }
}
