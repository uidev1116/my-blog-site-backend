<?php

class ACMS_GET_Media_Banner extends ACMS_GET
{
    function get()
    {
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        $order      = config('media_banner_order');
        $loopClass  = config('media_banner_loop_class');
        $aryStatus = configArray('media_banner_status');

        $items = [];
        $mids = [];
        $nth = 0;

        switch ($order) {
            case 'random':
                $keys = array_keys($aryStatus);
                shuffle($keys);
                $result = [];
                foreach ($keys as $key) {
                    $result[$key] = $aryStatus[$key];
                }
                $aryStatus = $result;
                break;
            case 'sort-desc':
                krsort($aryStatus);
                break;
            case 'sort-asc':
            default:
                break;
        }

        $limit = config('media_banner_limit');
        $int_display = 0;
        if (is_numeric($limit) && intval($limit) > 0) {
            //$aryStatus = array_slice($aryStatus, 0, $limit, true);
        } elseif (is_array($aryStatus)) { // @phpstan-ignore-line
            $limit = count($aryStatus);
        } else {
            $limit = 0;
        }


        foreach ($aryStatus as $i => $status) {
            if ($status !== 'true') {
                continue;
            }

            if ($int_display + 1 > $limit) {
                break;
            }

            $datestart = mb_convert_kana(config('media_banner_datestart', '', $i), "a", 'UTF-8');
            $timestart = mb_convert_kana(config('media_banner_timestart', '', $i), "a", 'UTF-8');
            $dateend = mb_convert_kana(config('media_banner_dateend', '', $i), "a", 'UTF-8');
            $timeend = mb_convert_kana(config('media_banner_timeend', '', $i), "a", 'UTF-8');

            $datestart = ( strlen($datestart) > 0 ) ? $datestart : '0000-01-01';
            $timestart = ( strlen($timestart) > 0 ) ? $timestart : '00:00:00';
            $dateend = ( strlen($dateend) > 0 ) ? $dateend : '9999-12-31';
            $timeend = ( strlen($timeend) > 0 ) ? $timeend : '23:59:59';

            if (! ( ( ($datestart . ' ' . $timestart) <= date('Y-m-d H:i:s', requestTime()) ) && ( date('Y-m-d H:i:s', requestTime()) <= ($dateend . ' ' . $timeend) ) )) {
                continue;
            }

            $item = [];
            $type = config('media_banner_type', '', $i);
            $source = config('media_banner_source', '', $i);
            $target = config('media_banner_target', '', $i);
            $mid = config('media_banner_mid', '', $i);
            $attr1 = config('media_banner_attr1', '', $i);
            $attr2 = config('media_banner_attr2', '', $i);
            $alt = config('media_banner_alt', '', $i);
            $link = config('media_banner_link', '', $i);

            if ($type === 'image') {
                if (!$mid) {
                    continue;
                }
                $item['banner#img'] = [
                    'target' => $target === "true" ? "_blank" : "_self",
                    'attr1' => $attr1,
                    'attr2' => $attr2,
                    'alt' => $alt,
                    'mid' => $mid,
                    'nth' => $nth,
                    'banner:loop.class' => $loopClass,
                    'url' => $link
                ];
                $mids[] = $mid;
            } elseif ($type === 'source') {
                $item['banner#src'] = [
                    'src' => $source,
                    'nth' => $nth,
                    'banner:loop.class' => $loopClass
                ];
            }
            $nth++;
            $int_display++;
            $items[] = $item;
        }

        $SQL = SQL::newSelect('media');
        $DB = DB::singleton(dsn());
        $SQL->addWhereIn('media_id', $mids);
        $query = $SQL->get(dsn());
        $row = $DB->query($query, 'all');

        foreach ($items as $i => $item) {
            foreach ($row as $media) {
                if (isset($item['banner#img']) && $item['banner#img']['mid'] == $media['media_id']) { // @phpstan-ignore-line
                    $size = $media['media_image_size'];
                    $items[$i]['banner#img']['x'] = preg_replace('/(\d*)?\sx\s(\d*)?/', '$1', $size);
                    $items[$i]['banner#img']['y'] = preg_replace('/(\d*)?\sx\s(\d*)?/', '$2', $size);
                    $items[$i]['banner#img']['img'] = Media::urlencode($media['media_path']) . Media::cacheBusting($media['media_update_date']);
                    $items[$i]['banner#img']['caption'] = $media['media_field_1'];
                    if (!$items[$i]['banner#img']['url']) {
                        $items[$i]['banner#img']['url'] = $media['media_field_2'];
                    }
                    $items[$i]['banner#img']['text'] = $media['media_field_4'];
                }
            }
            $items[$i]['banner:loop.class'] = $loopClass;
        }

        return setGlobalVars($Tpl->render(array_merge([
            'banner' => $items
        ], $this->getRootVars())));
    }

    /**
     * ルート変数を取得
     *
     * @return array<string, mixed>
     */
    protected function getRootVars(): array
    {
        return [
            'parent.loop.class' => config('media_banner_parent_loop_class'),
        ];
    }
}
