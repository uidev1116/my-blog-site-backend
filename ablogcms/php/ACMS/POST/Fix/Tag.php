<?php

class ACMS_POST_Fix_Tag extends ACMS_POST_Fix
{
    protected function init()
    {
        return true;
    }

    protected function process($data, $word)
    {
        return true;
    }

    protected function success()
    {
        return true;
    }

    public function post()
    {
        if (!sessionWithAdministration()) {
            return false;
        }
        $Fix = $this->extract('fix', new ACMS_Validator());
        $tagSource  = $Fix->get('tagSource');
        $source     = ( 1
            && is_uploaded_file($_FILES['source']['tmp_name'])
            && LocalStorage::isReadable($_FILES['source']['tmp_name'])
        ) || $tagSource;

        $Fix->setMethod('source', 'required', $source);
        $Fix->validate();

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);

            $this->init();

            $threshold      = $Fix->get('threshold');
            $certainly      = $Fix->get('certainly');
            $ignoreEntries  = $Fix->getArray('ignore');

            $words = [];
            if (empty($tagSource)) {
                try {
                    /**
                     * detect convert encoding
                     */
                    $uploadPath = $_FILES['source']['tmp_name'];
                    if (is_uploaded_file($uploadPath) === false) {
                        throw new \RuntimeException('無効なファイルです。');
                    }
                    $raw = LocalStorage::get($uploadPath, dirname($uploadPath));
                    if ($raw === false) {
                        throw new \RuntimeException('ファイルが見つかりません。');
                    }
                    if ($enc = mb_detect_encoding($raw, 'UTF-8, EUC-JP, SJIS-win, SJIS, EUCJP-win')) {
                        $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
                    }
                    $fixed = preg_replace('@,| |　@', '', $raw);
                    $words = preg_split('@[\x0D\x0A|\x0D|\x0A/]@', $fixed);
                    if ($words === false) {
                        throw new \RuntimeException('ワードが不正です');
                    }
                    $words = array_unique(array_merge(array_diff($words, [''])));

                    $Fix->set('tagSource', implode(',', $words));
                } catch (\Exception $e) {
                    return $this->Post;
                }
            } else {
                $words = explode(',', $tagSource);
            }

            $DB = DB::singleton(dsn());

            /**
             * word rotation & detect add tags
             */
            foreach ($words as $word) {
                $Tag    = SQL::newSelect('tag');
                $Tag->addSelect('tag_entry_id');
                $Tag->addWhereOpr('tag_name', $word);

                $SQL    = SQL::newSelect('entry');
                $SQL->addLeftJoin('fulltext', 'fulltext_eid', 'entry_id');
                $SQL->addLeftJoin('tag', 'tag_entry_id', 'entry_id');

                $SQL->addSelect('entry_id');
                $SQL->addSelect('entry_title');
                $SQL->addSelect('fulltext_value');
                $SQL->addSelect('tag_sort', 'tag_max', null, 'max');
                $SQL->addSelect('tag_name');

                $SQL->addWhereOpr('fulltext_value', '%' . $word . '%', 'LIKE');
                $SQL->addWhereOpr('entry_blog_id', BID);
                $SQL->addWhereNotIn('entry_id', $Tag, 'AND');

                $SQL->addGroup('entry_id');
                $q  = $SQL->get(dsn());
                $statement = $DB->query($q, 'exec');

                while ($e = $DB->next($statement)) {
                    $insert = false;
                    $eid    = $e['entry_id'];
                    $title  = $e['entry_title'];
                    $text   = $e['fulltext_value'];
                    $sort   = $e['tag_max'] + 1;

                    // continue when "is not set eid" OR "found on ignores list"
                    if (empty($eid) || array_search($eid . '@' . $word, $ignoreEntries, true) !== false) {
                        continue;
                    }

                    if ($certainly == 'on' && strstr($title, $word) !== false) {
                        $insert   = true;
                    } elseif (substr_count($text, $word) >= $threshold) {
                        $insert   = true;
                    }
                    if ($insert === true) {
                        $this->process($e, $word);
                    }
                }
            }
            $this->success();
        }
        return $this->Post;
    }
}
