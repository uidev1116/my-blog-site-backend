<?php

namespace Acms\Services\StaticExport\Generator;

use Acms\Services\StaticExport\Contracts\Generator;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\StaticExport\Entities\Page;
use React\Promise\PromiseInterface;
use React\Promise\Promise;

use function React\Async\await;

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
        return 'トップページの書き出し';
    }

    /**
     * @inheritDoc
     */
    public function run(): PromiseInterface
    {
        return new Promise(
            function (callable $resolve) {
                $blogUrl = acmsLink(['bid' => BID], false);

                $pages = [
                    new Page($blogUrl, 'index.html')
                ];

                if (!in_array('rss2.xml', $this->exclusionList, true)) {
                    $pages[] = new Page($blogUrl . 'rss2.xml', 'rss2.xml');
                }

                if (!in_array('sitemap.xml', $this->exclusionList, true)) {
                    $pages[] = new Page($blogUrl . 'sitemap.xml', 'sitemap.xml');
                }

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
