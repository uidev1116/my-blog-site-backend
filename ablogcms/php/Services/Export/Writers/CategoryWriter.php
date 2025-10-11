<?php

namespace Acms\Services\Export\Writers;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Common\Logger;
use XMLWriter;

class CategoryWriter
{
    /**
     * @var \Acms\Services\Export\Repositories\CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var \Acms\Services\Export\Helper;
     */
    protected $helper;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->categoryRepository = Application::make('export-repository-category');
        $this->helper =  Application::make('export-helper');
    }

    /**
     * カテゴリーアイテム配列を書き出し
     *
     * @param \XMLWriter $writer
     * @param int $bid
     * @param bool $includeChildBlogs
     * @param \Acms\Services\Common\Logger $logger
     * @return void
     */
    public function writeCategories(XMLWriter $writer, int $bid, bool $includeChildBlogs, Logger $logger): void
    {
        $sql = $this->categoryRepository->getCategoriesQuery($bid, $includeChildBlogs);
        $query = $sql->get(dsn());
        $statement = Database::query($query, 'exec');

        $logger->addMessage("カテゴリーを書き出し中...", 10, 1, false);
        while ($item = Database::next($statement)) {
            $this->writeCategoryElement($writer, $item);
        }
        sleep(3);
    }

    /**
     * ユーザーアイテムを書き出し
     *
     * @param \XMLWriter $writer
     * @param array $item
     * @return void
     */
    protected function writeCategoryElement(XMLWriter $writer, array $item): void
    {
        $cid = (int) $item['category_id'];

        $writer->startElement('wp:category');

        $writer->writeElement('wp:term_id', (string) $cid);
        $this->helper->writeCData($writer, 'wp:category_nicename', $item['category_code']);
        $this->helper->writeCData($writer, 'wp:category_parent', $item['category_parent'] ?: '');
        $this->helper->writeCData($writer, 'wp:cat_name', $item['category_name']);

        $writer->endElement();
    }
}
