<?php

namespace Acms\Services\Export;

use XMLWriter;

class Helper
{
    /**
     * 日時を RFC 2822 にフォーマット
     *
     * @param string $datetime
     * @return string
     */
    public function dateFormat(string $datetime): string
    {
        return date('D, d M Y H:i:s O', strtotime($datetime));
    }

    /**
     * 要素をCDATAで書き込む
     *
     * @param \XMLWriter $writer
     * @param string $name
     * @param string $data
     * @return void
     */
    public function writeCData(XMLWriter $writer, string $name, string $data): void
    {
        $writer->startElement($name);
        $writer->writeCdata($data);
        $writer->endElement();
    }

    /**
     * 一意なユーザーコードを取得
     *
     * @param string $code
     * @param string $email
     * @return string
     */
    public function computeUserCode(string $code, string $email): string
    {
        if (empty($code)) {
            return md5($email);
        }
        return $code;
    }
}
