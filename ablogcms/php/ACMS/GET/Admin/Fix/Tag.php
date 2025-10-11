<?php

class ACMS_GET_Admin_Fix_Tag extends ACMS_GET_Admin_Fix
{
    function fix(&$Tpl, $block)
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $Fix =& $this->Post->getChild('fix');

        $threshold      = $Fix->get('threshold');
        $certainly      = $Fix->get('certainly');

        if ($Fix->isNull()) {
            $Fix->set('threshold', '3');
            $Fix->set('certainly', 'on');
        }

        if ($this->Post->get('preview') === 'on') {
            $words = $this->Post->getArray('word');
            if (empty($words)) {
                $Tpl->add(array_merge(['notFound', 'preview'], $block));
            }
            foreach ($words as $i => $word) {
                $Entries = $this->Post->getChild('data' . $i);

                foreach ($Entries->getArray('title') as $j => $title) {
                    $fulltext = $Entries->get('fulltext', '', $j);
                    $fulltext = preg_replace('@(' . $word . ')@', '<strong class="highlight">$1</strong>', $fulltext);

                    $Tpl->add(array_merge(['entry:loop', 'word:loop', 'preview'], $block), [
                        'title'     => $title,
                        'fulltext'  => $fulltext,
                        'word'      => $Entries->get('word', '', $j),
                        'eid'       => $Entries->get('eid', '', $j),
                    ]);
                }
                $Tpl->add(array_merge(['word:loop', 'preview'], $block), [
                    'word'  => $word,
                ]);
            }
        }

        return true;
    }
}
