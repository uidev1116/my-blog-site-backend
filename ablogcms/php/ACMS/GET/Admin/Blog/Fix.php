<?php

class ACMS_GET_Admin_Blog_Fix extends ACMS_GET_Admin
{
    function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        if (0 !== ACMS_RAM::blogParent(BID)) {
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        if (!$this->Post->isValid()) {
            $Tpl->add('error');
        } else {
            if ('success' == $this->Post->get('delete')) {
                $Tpl->add('delete#success');
            }
            if ('success' == $this->Post->get('align')) {
                $Tpl->add('align#success');
            }

            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newSelect('blog');
            $SQL->setSelect('blog_id');
            $SQL->setGroup('blog_id');
            $SQL->setHaving(SQL::newOpr(SQL::newFunction('blog_id', 'count'), 2, '>='));
            $SQL->setLimit(1);

            if (!!($bid = intval($DB->query($SQL->get(dsn()), 'one')))) {
                $SQL    = SQL::newSelect('blog');
                $SQL->addWhereOpr('blog_id', $bid);
                $i  = 1;
                foreach ($DB->query($SQL->get(dsn()), 'all') as $row) {
                    $Tpl->add('status:touch#' . $row['blog_status']);
                    $Tpl->add('indexing:touch#' . $row['blog_indexing']);
                    $Tpl->add('blog:loop', [
                        'i'         => $i++,
                        'bid'       => $row['blog_id'],
                        'code'      => $row['blog_code'],
                        'status'    => $row['blog_status'],
                        'parent'    => $row['blog_parent'],
                        'sort'      => $row['blog_sort'],
                        'left'      => $row['blog_left'],
                        'right'     => $row['blog_right'],
                        'name'      => $row['blog_name'],
                        'domain'    => $row['blog_domain'],
                        'indexing'  => $row['blog_indexing'],
                    ]);
                }
            } else {
                $Tpl->add('align');
            }
        }


        return $Tpl->get();
    }
}
