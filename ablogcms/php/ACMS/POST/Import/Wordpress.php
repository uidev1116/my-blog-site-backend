<?php

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Entry;

class ACMS_POST_Import_Wordpress extends ACMS_POST_Import
{
    /**
     * @return void
     */
    public function init()
    {
        @set_time_limit(-1);
        $this->importType = 'WordPress';
        $this->uploadFiledName = 'wordpress_import_file';
        $this->importCid = intval($this->Post->get('category_id'));
        if (intval($this->importCid) == 0) {
            $this->importCid = null;
        } else {
            $this->Post->set('categoryName', ACMS_RAM::categoryName($this->importCid) . 'カテゴリー');
        }
    }

    /**
     * @return void
     */
    public function import()
    {
        $this->httpFile->validateFormat(['xml']);
        $path = $this->httpFile->getPath();
        $data = LocalStorage::get($path, dirname($path));
        if ($data === false) {
            throw new RuntimeException('ファイルの読み込みに失敗しました。');
        }
        if ($data !== '') {
            $data = LocalStorage::removeIllegalCharacters($data); // 不正な文字コードを削除
        }
        $this->validateXml($data);

        $xml = new XMLReader();
        $xml->XML($data); // @phpstan-ignore-line

        $entryIndex = 0;
        while ($xml->read()) {
            if ($xml->name === 'item' and intval($xml->nodeType) === XMLReader::ELEMENT) {
                $title = $this->getNodeValue($xml, 'title');
                $content = $this->getNodeValue($xml, 'content:encoded');
                $date = $this->getNodeValue($xml, 'wp:post_date');
                $status = $this->getNodeValue($xml, 'wp:status');
                $type = $this->getNodeValue($xml, 'wp:post_type');

                if ($type !== 'post' && $type !== 'page') {
                    continue; // 投稿タイプが「投稿」「固定ページ」でなかった場合はスキップ
                }

                $entryIndex++;

                $tags = [];
                $fields = [];
                $status = $this->convertStatus($status);

                // 本文からサムネイル画像のパスを抜き出し
                if (preg_match('/<\s*img(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+src\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/ui', $content, $matches)) {
                    if ($matches[1]) {
                        $fields[] = [
                            'key' => 'wp_thumbnail_url',
                            'value' => trim($matches[1], '"\''),
                        ];
                    }
                }

                while ($xml->read()) {
                    // エントリーを作成
                    if (intval($xml->nodeType) === XMLReader::END_ELEMENT and $xml->name === 'item') {
                        //insert
                        if ($title === '') {
                            $title = '空のタイトル';
                        }
                        if ($date === '') {
                            $date = date('Y-m-d H:i:s', REQUEST_TIME);
                        }
                        if ($status === '') {
                            $status = 'close';
                        }
                        $entry = [
                            'title'     => $title,
                            'content'   => $this->buildMoreContent($content),
                            'date'      => $date,
                            'status'    => $status,
                            'tags'      => $tags,
                            'fields'    => $fields,
                        ];
                        try {
                            $this->validateEntry($entry);
                            $this->insertEntry($entry);
                        } catch (\Throwable $e) {
                            $this->recordImportError($entryIndex, $e->getMessage());
                        }
                        break;
                    }
                    // タグ
                    if ($xml->name === 'category' and $xml->getAttribute('domain') === 'post_tag' and intval($xml->nodeType) === XMLReader::ELEMENT) {
                        $xml->read();
                        $tags[] = $xml->value;
                    }
                    // カスタムフィールド
                    if ($xml->name === 'wp:postmeta' and intval($xml->nodeType) === XMLReader::ELEMENT) {
                        $key    = $this->getNodeValue($xml, 'wp:meta_key');
                        $value  = $this->getNodeValue($xml, 'wp:meta_value');
                        if (!preg_match('/^_.*/', $key)) {
                            $fields[] = [
                                'key' => $key,
                                'value' => $value,
                            ];
                        } elseif ($key === '_thumbnail_id') {
                            // アイキャッチの画像IDを保存
                            $fields[] = [
                                'key' => 'wp_thumbnail_id',
                                'value' => $value,
                            ];
                        }
                    }
                }
            }
        }
        $xml->close();
    }

    /**
     * @param string $status
     * @return string
     */
    private function convertStatus($status)
    {
        switch ($status) {
            case 'publish':
                $status = 'open';
                break;
            case 'draft':
                $status = 'draft';
                break;
            default:
                $status = 'close';
        }
        return $status;
    }

    /**
     * @param \XMLReader $xml
     * @param string $node
     * @return string
     */
    protected function getNodeValue(&$xml, $node)
    {
        $nodeValue = '';
        while ($xml->read()) {
            if ($xml->name === $node) {
                $xml->read();
                $nodeValue = $xml->value;
                break;
            }
            if (intval($xml->nodeType) === XMLReader::END_ELEMENT and $xml->name === $node) {
                break;
            }
        }
        return $nodeValue;
    }

    /**
     * @param string $content
     * @return array<int, string>
     */
    private function buildMoreContent($content)
    {
        return explode('<!--more-->', $content, 2);
    }

    /**
     * @param string $data
     * @return void
     */
    protected function validateXml($data)
    {
        $reader = new XMLReader();
        $reader->XML($data);
        $reader->setParserProperty(XMLReader::VALIDATE, true);
        if (!$reader->isValid()) {
            $reader->close();
            throw new RuntimeException('XMLファイルが正しくありません。または正しいエクスポートファイルではありません。');
        }
        $reader->close();
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

        // 3. タグのバリデーション
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
