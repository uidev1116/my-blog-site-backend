<?php

class ACMS_POST_Fix_Image extends ACMS_POST_Fix
{
    /**
     * @var int
     */
    public $resize;

    /**
     * @var string
     */
    public $stdSide;

    public function post()
    {
        if (!sessionWithAdministration()) {
            return false;
        }

        $Fix = $this->extract('fix', new ACMS_Validator());
        $Fix->setMethod('fix_image_type', 'required');
        $Fix->setMethod('fix_image_size', 'required');
        $Fix->setMethod('fix_image_size', 'regex', '^[1-9][\d]+$');

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            $DB = DB::singleton(dsn());

            $type           = $Fix->get('fix_image_type');
            $normalSize     = $Fix->get('fix_image_normal_size');
            $this->resize   = intval($Fix->get('fix_image_size'));
            $this->stdSide  = $Fix->get('fix_image_size_criterion');

            $SQL = SQL::newSelect('column');
            $SQL->addSelect('column_id');
            $SQL->addSelect('column_field_2');
            $SQL->addWhereOpr('column_type', 'image');
            $SQL->addWhereOpr('column_blog_id', BID);
            if ($type == 'normal') {
                $SQL->addWhereOpr('column_size', $normalSize);
            }

            $all    = $DB->query($SQL->get(dsn()), 'all');
            foreach ($all as $row) {
                $this->resize($type, $row);
            }

            $this->Post->set('message', 'success');

            $typeName = '';
            if ($type === 'large') {
                $typeName = '拡大画像';
            }
            if ($type === 'normal') {
                $typeName = '通常画像（' . $normalSize . '）';
            }
            if ($type === 'tiny') {
                $typeName = 'モバイル画像';
            }
            if ($type === 'square') {
                $typeName = '正方形画像';
            }

            $sizeName = '';
            if ($this->stdSide === 'width') {
                $sizeName = '横' . $this->resize;
            }
            if ($this->stdSide === 'height') {
                $sizeName = '縦' . $this->resize;
            }
            if (empty($this->stdSide)) {
                $sizeName = '長辺' . $this->resize;
            }

            AcmsLogger::info('データ修正ツールで、「' . $typeName . '」を「' . $sizeName . '」にリサイズしました');
        }

        return $this->Post;
    }

    function resize($type, $column)
    {
        $path       = $column['column_field_2'];
        $pfx        = ('normal' == $type) ? '' : $type . '-';
        $target     = '';

        // 各種サイズ
        $target = preg_replace('@(.*/)([^/]*)$@', '$1' . $pfx . '$2', $path);
        if (!preg_match('@\.([^.]+)$@', $target, $match)) {
            return false;
        }
        $ext    = $match[1];

        $_file      = ARCHIVES_DIR . $target;

        // Large
        $_largePath = preg_replace('@(.*/)([^/]*)$@', '$1large-$2', $path);
        if ($xy = PublicStorage::getImageSize(ARCHIVES_DIR . $_largePath)) {
            $target = $_largePath;
        }

        $_width     = null;
        $_height    = null;
        $_size      = $this->resize;
        $_angle     = null;

        $editTarget = ARCHIVES_DIR . $target;
        $_stdSide   = $this->stdSide;

        // long side
        if ($xy = PublicStorage::getImageSize($editTarget)) {
            if (!empty($_stdSide)) {
            } elseif ($xy[0] >= $xy[1]) {
                $_stdSide = 'width';
            } else {
                $_stdSide = 'height';
            }
        } else {
            return false;
        }

        // square
        if ($type == 'square') {
            $_width  = $_size;
            $_height = $_size;
        // normal, tiny, large
        } else {
            if ($_stdSide  === 'w' || $_stdSide === 'width') {
                $_width = $_size;
                $_size  = null;
            }
            if ($_stdSide  === 'h' || $_stdSide === 'height') {
                $_height = $_size;
                $_size   = null;
            }
        }

        Image::resizeImg($editTarget, $_file, $ext, $_width, $_height, $_size, $_angle);

        if ($type == 'normal') {
            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newUpdate('column');
            $SQL->addUpdate('column_size', $_stdSide . $this->resize);
            $SQL->addWhereOpr('column_type', 'image');
            $SQL->addWhereOpr('column_blog_id', BID);
            $SQL->addWhereOpr('column_id', $column['column_id']);
            $DB->query($SQL->get(dsn()), 'exec');
        }
    }
}
