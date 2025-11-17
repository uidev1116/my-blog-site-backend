<?php

use Acms\Services\Common\MimeTypeValidator;

class ACMS_POST_Import_MovableType extends ACMS_POST_Import
{
    protected $importCid;
    protected $csvLabels;

    function init()
    {
        $this->importType = 'Movable Type';
        $this->uploadFiledName = 'mt_import_file';
        $this->importCid = intval($this->Post->get('category_id'));

        if (intval($this->importCid) == 0) {
            $this->importCid = null;
        } elseif (intval($this->importCid) == -1) {
            $this->Post->set('categoryName', 'MTカテゴリー');
        } else {
            $this->Post->set('categoryName', ACMS_RAM::categoryName($this->importCid) . 'カテゴリー');
        }
    }

    function import()
    {
        $path = $this->httpFile->getPath();
        $this->validate($path);

        $entryBlock = '';
        $handle = fopen($path, "r");
        if ($handle === false) {
            throw new RuntimeException('ファイルのオープンに失敗しました。');
        }
        rewind($handle);

        while (($buffer = fgets($handle)) !== false) {
            if (preg_match('@^--------$@m', $buffer)) {
                $this->buildEntryBlock($entryBlock);
                $entryBlock = '';
            } else {
                $entryBlock .= $buffer;
            }
        }
        fclose($handle);
    }

    function validate($path)
    {
        $mimeValidator = new MimeTypeValidator();
        $mime = $mimeValidator->sniffMimeType($path);
        if (!$mime) {
            throw new RuntimeException(gettext('ファイル形式が不明です'));
        }
        $extensions = $mimeValidator->getExtensionsFromMimeType($mime);
        if (!in_array('txt', $extensions, true) && !in_array('html', $extensions, true)) {
            throw new RuntimeException(gettext('ファイル形式が不正です'));
        }
        $handle = @fopen($path, "r");
        if ($handle) {
            while (fgets($handle) !== false) {
            }
            if (!feof($handle)) {
                throw new RuntimeException('ファイルが壊れている可能性があります。');
            }
            @fclose($handle);
            return true;
        } else {
            throw new RuntimeException('ファイルの読み込みに失敗しました。');
        }
    }

    function buildEntryBlock($entryBlock)
    {
        $meta_regex = '@^(.*?): (.*?)$@si';
        $body_regex = '@^[\x0D\x0A|\x0D|\x0A/\n]*(.*?):[\x0D\x0A|\x0D|\x0A/\n]@si';

        $content    = preg_split('@^-----$@m', $entryBlock);
        if ($content === false || count($content) < 2) {
            throw new RuntimeException('エントリーのフォーマットが不正です。');
        }
        $meta       = array_splice($content, 0, 1);
        $body       = array_splice($content, 0);
        $entry      = [];

        /**
        * get meta data
        */
        foreach (preg_split('@[\x0D\x0A|\x0D|\x0A]@', $meta[0]) as $row) {
            preg_match($meta_regex, $row, $match);
            if (!empty($match)) {
                $key    = $match[1];
                $val    = $match[2];
                $entry[$key]    = $val;
            }
        }

        /**
        * get body data
        */
        foreach ($body as $row) {
            preg_match($body_regex, $row, $match);
            if (!empty($match)) {
                $key = $match[1];
                $val = preg_replace($body_regex, '', $row);
                $entry[$key]    = $val;
            }
        }
        $this->convertMtContents($entry);
    }

    function convertMtContents($entry)
    {
        $tags       = [];
        $content    = [];
        $category   = null;
        $ecode      = null;

        $date   = $this->convertMtDate($entry['DATE']);
        $status = $this->convertMtStatus($entry['STATUS']);
        if (isset($entry['TAGS']) and !empty($entry['TAGS'])) {
            $tags   = $this->convertMtTags($entry['TAGS']);
        }
        $content[]  = $entry['BODY'];
        if (isset($entry['EXTENDED BODY']) && strlen($entry['EXTENDED BODY']) > 1) {
            $content[]  = $entry['EXTENDED BODY'];
        }
        if (isset($entry['PRIMARY CATEGORY']) and !empty($entry['PRIMARY CATEGORY'])) {
            $category   = $entry['PRIMARY CATEGORY'];
        } elseif (isset($entry['CATEGORY']) and !empty($entry['CATEGORY'])) {
            $category   = $entry['CATEGORY'];
        }
        if (isset($entry['BASENAME']) and !empty($entry['BASENAME'])) {
            $ecode   = $entry['BASENAME'];
        }

        if (intval($this->importCid) != -1) {
            $category = null;
        }

        $entry = [
            'title'     => $entry['TITLE'],
            'content'   => $content,
            'date'      => $date,
            'status'    => $status,
            'tags'      => $tags,
            'category'  => $category,
            'ecode'     => $ecode,
        ];

        $this->insertEntry($entry);
    }

    function convertMtDate($date)
    {
        return date('Y-m-d H:i:s', strtotime($date));
    }

    function convertMtStatus($status)
    {
        $status = strtoupper($status);

        switch ($status) {
            case 'PUBLISH':
                $status  = 'open';
                break;
            case 'DRAFT':
                $status = 'draft';
                break;
            default:
                $status = 'close';
                break;
        }
        return $status;
    }

    function convertMtTags($tagStr)
    {
        $tagStr =  preg_replace("@\"@", "", $tagStr);
        return explode(',', $tagStr);
    }
}
