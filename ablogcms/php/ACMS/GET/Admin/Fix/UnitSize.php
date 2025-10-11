<?php

class ACMS_GET_Admin_Fix_UnitSize extends ACMS_GET_Admin_Fix
{
    function fix(&$Tpl, $block)
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $DB     = DB::singleton(dsn());
        $Fix    =& $this->Post->getChild('fix');

        //-------
        // size
        $unitSizeUnitType   = $Fix->get('unit_size_unit_type');
        $unitSizeSizeType   = $Fix->get('unit_size_size_type');

        if (!empty($unitSizeUnitType) && !empty($unitSizeSizeType)) {
            $SQL    = SQL::newSelect('column');
            $column = 'column_size';
            if ($unitSizeSizeType === 'display') {
                switch ($unitSizeUnitType) {
                    case 'youtube':
                    case 'video':
                        $column = 'column_field_3';
                        break;
                    case 'image':
                    case 'osmap':
                    case 'map':
                        $column = 'column_field_5';
                        break;
                    case 'eximage':
                    case 'media':
                        $column = 'column_field_6';
                        break;
                }
            }
            $SQL->addSelect($column, null, null, 'DISTINCT');
            $SQL->addWhereOpr('column_type', $unitSizeUnitType);
            $SQL->addWhereOpr('column_blog_id', BID);

            $all    = $DB->query($SQL->get(dsn()), 'all');
            foreach ($all as $size) {
                $size = $size[$column];
                if (empty($size)) {
                    continue;
                }
                $Tpl->add(array_merge(['unit_size:loop'], $block), [
                    'unit_size' => $size,
                ]);
            }
        }

        return true;
    }
}
