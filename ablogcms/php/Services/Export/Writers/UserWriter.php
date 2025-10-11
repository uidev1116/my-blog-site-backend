<?php

namespace Acms\Services\Export\Writers;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Common\Logger;
use XMLWriter;

class UserWriter
{
    /**
     * @var \Acms\Services\Export\Repositories\UserRepository
     */
    protected $userRepository;

    /**
     * @var \Acms\Services\Export\Helper;
     */
    protected $helper;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->userRepository = Application::make('export-repository-user');
        $this->helper =  Application::make('export-helper');
    }

    /**
     * ユーザーアイテム配列を書き出し
     *
     * @param \XMLWriter $writer
     * @param int $bid
     * @param bool $includeChildBlogs
     * @param \Acms\Services\Common\Logger $logger
     * @return void
     */
    public function writeUsers(XMLWriter $writer, int $bid, bool $includeChildBlogs, Logger $logger): void
    {
        $sql = $this->userRepository->getUsersQuery($bid, $includeChildBlogs);
        $query = $sql->get(dsn());
        $statement = Database::query($query, 'exec');

        $logger->addMessage("ユーザーを書き出し中...", 10, 1, false);
        while ($item = Database::next($statement)) {
            $this->writeUserElement($writer, $item);
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
    protected function writeUserElement(XMLWriter $writer, array $item): void
    {
        $uid = (int) $item['user_id'];
        $writer->startElement('wp:author');

        $writer->writeElement('wp:author_id', (string) $uid);
        $this->helper->writeCData($writer, 'wp:author_login', $this->helper->computeUserCode($item['user_code'], $item['user_mail']));
        $this->helper->writeCData($writer, 'wp:author_email', $item['user_mail']);
        $this->helper->writeCData($writer, 'wp:author_display_name', $item['user_name']);

        $writer->endElement();
    }
}
