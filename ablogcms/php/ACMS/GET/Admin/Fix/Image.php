<?php

class ACMS_GET_Admin_Fix_Image extends ACMS_GET_Admin_Fix
{
    function fix(&$Tpl, $block)
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $DB     = DB::singleton(dsn());
        $Fix    =& $this->Post->getChild('fix');
        $target = $Fix->get('fix_image_normal_size');

        $SQL    = SQL::newSelect('column');
        $SQL->addWhereOpr('column_type', 'image');
        $SQL->addWhereOpr('column_blog_id', BID);
        $SQL->addSelect('column_size');
        $SQL->addGroup('column_size');
        $all    = $DB->query($SQL->get(dsn()), 'all');

        foreach ($all as $row) {
            $size = $row['column_size'];
            if (empty($size)) {
                continue;
            }
            $loop = [
                'size'  => $size,
            ];
            if ($size === $target) {
                $loop['selected'] = config('attr_selected');
            }
            $Tpl->add(array_merge(['normalSize:loop'], $block), $loop);
        }

        $vars = [
            'largeSize'     => config('image_size_large'),
            'tinySize'      => config('image_size_tiny'),
            'squareSize'    => config('image_size_square'),
        ];
        $Tpl->add(array_merge(['size'], $block), $vars);

        return true;
    }
}
