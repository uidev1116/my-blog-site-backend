<?php

namespace Acms\Services\Export\Engines;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PrivateStorage;
use Acms\Services\Facades\Common;
use XMLWriter;
use Exception;

class WxrEngine
{
    /**
     * @var \Acms\Services\Export\Repositories\BlogRepository
     */
    protected $blogRepository;

    /**
     * @var \Acms\Services\Export\Writers\EntryWriter
     */
    protected $entryWriter;

    /**
     * @var \Acms\Services\Export\Writers\UserWriter
     */
    protected $userWriter;

    /**
     * @var \Acms\Services\Export\Writers\CategoryWriter
     */
    protected $categoryWriter;

    /**
     * @var int
     */
    protected $targetBlogId;

    /**
     * @var bool
     */
    protected $includeChildBlogs;

    /**
     * @var \Acms\Services\Export\Helper;
     */
    protected $helper;

    /**
     * @var \Acms\Services\Common\Logger
     */
    protected $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->blogRepository = Application::make('export-repository-blog');
        $this->entryWriter = Application::make('export-writer-entry');
        $this->userWriter = Application::make('export-writer-user');
        $this->categoryWriter = Application::make('export-writer-category');
        $this->helper = Application::make('export-helper');
        $this->logger = Application::make('common.logger');
    }

    /**
     * エクスポート実行
     *
     * @param int $bid
     * @param bool $includeChildBlogs
     * @param string $outputPath
     * @return void
     */
    public function export(int $bid, bool $includeChildBlogs, string $outputPath): void
    {
        $lockService = Application::make('export-wxr-lock');

        try {
            $lockService->tryLock();
            $this->targetBlogId = $bid;
            $this->includeChildBlogs = $includeChildBlogs;

            $this->logger->setDestinationPath(CACHE_DIR . 'wxr-export-logger.json');
            $this->logger->init();

            $writer = $this->createWriter($outputPath);
            $this->outputWxr($writer);

            if (!Common::isLocalPrivateStorage()) {
                if ($content = LocalStorage::get($outputPath)) {
                    PrivateStorage::put($outputPath, $content);
                }
                LocalStorage::remove($outputPath);
            }
            $this->logger->addMessage('エクスポート完了', 100, 1, false);
            $this->logger->success();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        } finally {
            $lockService->release();
            sleep(5);
            $this->logger->terminate();
        }
    }

    /**
     * XMLWriter を生成
     *
     * @param string $outputPath
     * @return \XMLWriter
     */
    protected function createWriter(string $outputPath): XMLWriter
    {
        LocalStorage::makeDirectory(dirname($outputPath));

        $writer = new XMLWriter();
        $writer->openUri($outputPath);
        $writer->setIndent(true); // インデントを有効にする
        $writer->setIndentString('  '); // スペース2つでインデントを設定

        return $writer;
    }

    /**
     * 書き出しを開始
     *
     * @param \XMLWriter $writer
     * @return void
     */
    protected function outputWxr(XMLWriter $writer): void
    {
        $writer->startDocument('1.0', 'UTF-8');

        $this->writeRootElement($writer); // RSSルート

        $writer->endDocument();
        $writer->flush();
    }

    /**
     * ルート要素を書き出し
     *
     * @param \XMLWriter $writer
     * @return void
     */
    protected function writeRootElement(XMLWriter $writer): void
    {
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:excerpt', 'http://wordpress.org/export/1.2/excerpt/');
        $writer->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $writer->writeAttribute('xmlns:wfw', 'http://wellformedweb.org/CommentAPI/');
        $writer->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $writer->writeAttribute('xmlns:wp', 'http://wordpress.org/export/1.2/');
        $this->writeChannelElement($writer); // チャンネル要素

        $writer->endElement();
    }

    /**
     * チャンネル要素を書き出し
     *
     * @param \XMLWriter $writer
     * @return void
     */
    protected function writeChannelElement(XMLWriter $writer): void
    {
        $blogField = $this->blogRepository->getBlogField($this->targetBlogId);

        $writer->startElement('channel');
        $writer->writeElement('title', $this->blogRepository->getBlogName($this->targetBlogId));
        $writer->writeElement('link', $this->blogRepository->getBlogUrl($this->targetBlogId));
        $writer->writeElement('description', $blogField->get('blog_meta_description'));

        $writer->writeElement('pubDate', $this->helper->dateFormat($this->blogRepository->getBlogGeneratedDatetime($this->targetBlogId)));
        $writer->writeElement('language', 'ja');
        $writer->writeElement('wp:wxr_version', '1.2');
        $writer->writeElement('wp:base_site_url', $this->blogRepository->getBlogUrl(RBID)); // @phpstan-ignore-line
        $writer->writeElement('wp:base_blog_url', $this->blogRepository->getBlogUrl($this->targetBlogId));
        $writer->writeElement('generator', 'a-blog cms v' . VERSION);

        $this->writeItems($writer);

        $writer->endElement();
    }

    /**
     * アイテム配列を書き出し
     *
     * @param \XMLWriter $writer
     * @return void
     */
    protected function writeItems(XMLWriter $writer): void
    {
        $this->userWriter->writeUsers($writer, $this->targetBlogId, $this->includeChildBlogs, $this->logger);
        $this->categoryWriter->writeCategories($writer, $this->targetBlogId, $this->includeChildBlogs, $this->logger);
        $this->entryWriter->writeEntries($writer, $this->targetBlogId, $this->includeChildBlogs, $this->logger);
    }
}
