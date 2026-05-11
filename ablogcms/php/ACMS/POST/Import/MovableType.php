<?php

use Acms\Services\Common\MimeTypeValidator;
use Acms\Services\Facades\Entry;

class ACMS_POST_Import_MovableType extends ACMS_POST_Import
{
    /** @var int|null */
    protected $importCid;

    /**
     * @return void
     */
    public function init()
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

    /**
     * @return void
     */
    public function import()
    {
        $path = $this->httpFile->getPath();
        $this->validate($path);

        $entryBlock = '';
        $entryIndex = 0;
        $handle = fopen($path, "r");
        if ($handle === false) {
            throw new RuntimeException('ファイルのオープンに失敗しました。');
        }
        rewind($handle);

        while (($buffer = fgets($handle)) !== false) {
            if (preg_match('@^--------$@m', $buffer)) {
                $entryIndex++;
                try {
                    $this->buildEntryBlock($entryBlock);
                } catch (\Throwable $e) {
                    $this->recordImportError($entryIndex, $e->getMessage());
                }
                $entryBlock = '';
            } else {
                $entryBlock .= $buffer;
            }
        }
        fclose($handle);
    }

    /**
     * @param string $path
     * @return void
     */
    private function validate($path)
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
            return;
        }
        throw new RuntimeException('ファイルの読み込みに失敗しました。');
    }

    /**
     * @param string $entryBlock
     * @return void
     */
    private function buildEntryBlock($entryBlock)
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
            if (count($match) > 0) {
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
            if (count($match) > 0) {
                $key = $match[1];
                $val = preg_replace($body_regex, '', $row);
                $entry[$key]    = $val;
            }
        }
        $this->convertMtContents($entry);
    }

    /**
     * @param array<string, string|null> $entry
     * @return void
     */
    private function convertMtContents(array $entry)
    {
        $tags       = [];
        $content    = [];
        $category   = null;
        $ecode      = null;

        $date   = $this->convertMtDate($entry['DATE'] ?? '');
        $status = $this->convertMtStatus($entry['STATUS'] ?? '');
        if (isset($entry['TAGS']) && $entry['TAGS'] !== '') {
            $tags   = $this->convertMtTags($entry['TAGS']);
        }
        $content[]  = $entry['BODY'];
        if (isset($entry['EXTENDED BODY']) && strlen($entry['EXTENDED BODY']) > 1) {
            $content[]  = $entry['EXTENDED BODY'];
        }
        if (isset($entry['PRIMARY CATEGORY']) && $entry['PRIMARY CATEGORY'] !== '') {
            $category   = $entry['PRIMARY CATEGORY'];
        } elseif (isset($entry['CATEGORY']) && $entry['CATEGORY'] !== '') {
            $category   = $entry['CATEGORY'];
        }
        if (isset($entry['BASENAME']) && $entry['BASENAME'] !== '') {
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

        $this->validateEntry($entry);
        $this->insertEntry($entry);
    }

    /**
     * @param string $date
     * @return string
     */
    private function convertMtDate($date)
    {
        return date('Y-m-d H:i:s', strtotime($date));
    }

    /**
     * @param string $status
     * @return string
     */
    private function convertMtStatus($status)
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

    /**
     * @param string $tagStr
     * @return array<int, string>
     */
    private function convertMtTags($tagStr)
    {
        $tagStr = preg_replace("@\"@", "", $tagStr);
        return explode(',', is_string($tagStr) ? $tagStr : '');
    }

    /**
     * エントリーデータのバリデーション
     *
     * @param array<string, mixed> $entry エントリーデータ
     * @return void
     * @throws RuntimeException バリデーションエラー時
     */
    private function validateEntry(array $entry)
    {
        // 1. 基本的なフォーマットチェック
        $this->validateBasicFormat($entry);

        // 2. 文字数制限チェック
        $this->validateFieldLengths($entry);

        // 3. エントリーコードのバリデーション
        $this->validateEntryCode($entry);

        // 4. タグのバリデーション
        $this->validateTags($entry);
    }

    /**
     * 基本的なフォーマットチェック
     *
     * @param array<string, mixed> $entry エントリーデータ
     * @return void
     * @throws RuntimeException フォーマットが不正な場合
     */
    private function validateBasicFormat(array $entry)
    {
        // タイトルの存在確認
        if (!isset($entry['title']) || $entry['title'] === '') {
            throw new RuntimeException('タイトルが指定されていません。');
        }

        // ステータスの値チェック
        if (!in_array($entry['status'], ['open', 'close', 'draft'], true)) {
            throw new RuntimeException('不正なステータスが設定されています。open, close, draft のいずれかを指定してください。');
        }

        // 日時のフォーマットチェック
        if (!preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $entry['date'])) {
            throw new RuntimeException('日時のフォーマットが間違っています。YYYY-MM-DD HH:MM:SS 形式で指定してください。');
        }
    }

    /**
     * 文字数制限チェック
     *
     * @param array<string, mixed> $entry エントリーデータ
     * @return void
     * @throws RuntimeException 文字数制限を超えている場合
     */
    private function validateFieldLengths(array $entry)
    {
        // entry_title: varchar(255)
        if (isset($entry['title']) && $entry['title'] !== '') {
            $length = mb_strlen($entry['title'], 'UTF-8');
            if ($length > 255) {
                throw new RuntimeException('タイトルが長すぎます。最大255文字まで入力できます。現在: ' . $length . '文字');
            }
        }

        // entry_code (ecode): varchar(64) - 拡張子をつけた後の文字数でチェック
        if (isset($entry['ecode']) && $entry['ecode'] !== '') {
            $code = $entry['ecode'];
            // 拡張子が含まれていない場合は拡張子を追加
            $code = Entry::formatEntryCode($code);
            $length = mb_strlen($code, 'UTF-8');
            if ($length > 64) {
                throw new RuntimeException('エントリーコードが長すぎます。最大64文字まで入力できます（拡張子を含む）。現在: ' . $length . '文字');
            }
        }
    }

    /**
     * エントリーコードのバリデーション
     *
     * @param array<string, mixed> $entry エントリーデータ
     * @return void
     * @throws RuntimeException エントリーコードが不正な場合
     */
    private function validateEntryCode(array $entry)
    {
        if (!isset($entry['ecode']) || $entry['ecode'] === '') {
            return; // エントリーコードが指定されていない場合はスキップ（自動生成される）
        }

        $code = $entry['ecode'];
        // 拡張子が含まれていない場合は拡張子を追加してチェック
        $code = Entry::formatEntryCode($code);

        // 形式チェック
        if (!isValidCode($code)) {
            throw new RuntimeException('エントリーコードの形式が正しくありません。改行、タブ、制御文字、引用符を含むことはできません。');
        }

        // 予約語チェック
        if (isReserved($code, false)) {
            throw new RuntimeException('エントリーコードに予約語が使用されています。別のコードを指定してください。');
        }

        // 重複チェック
        if (config('check_duplicate_entry_code') === 'on') {
            $categoryId = null;
            if (isset($this->importCid) && $this->importCid > 0) {
                $categoryId = $this->importCid;
            } elseif (isset($entry['category']) && $entry['category'] !== '') {
                // カテゴリー名からカテゴリーIDを取得（insertCategory で作成される可能性があるため、ここではチェックしない）
                $categoryId = null;
            }
            if (Entry::validEntryCodeDouble($code, BID, $categoryId)) {
                throw new RuntimeException('エントリーコードが重複しています。別のコードを指定してください。');
            }
        }
    }

    /**
     * タグのバリデーション
     *
     * @param array<string, mixed> $entry エントリーデータ
     * @return void
     * @throws RuntimeException タグが不正な場合
     */
    private function validateTags(array $entry)
    {
        if (!isset($entry['tags']) || !is_array($entry['tags']) || count($entry['tags']) === 0) {
            return;
        }

        $tags = $entry['tags'];

        // タグ名を正規化（空文字列を除去）
        $validTags = array_filter($tags, function ($tag) {
            return trim($tag) !== '';
        });

        if (count($validTags) > 0) {
            Entry::validateTagNames($validTags);
        }
    }
}
