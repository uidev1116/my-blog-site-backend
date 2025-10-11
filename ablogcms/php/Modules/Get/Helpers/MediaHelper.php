<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Database;
use SQL;
use Field;

class MediaHelper extends BaseHelper
{
    public function getMediaBannerData(Field $config): array
    {
        $order      = $config->get('media_banner_order');
        $loopClass  = $config->get('media_banner_loop_class');
        $aryStatus = $config->getArray('media_banner_status');

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

        $limit = $config->get('media_banner_limit');
        $int_display = 0;
        if (is_numeric($limit) && intval($limit) > 0) {
            //$aryStatus = array_slice($aryStatus, 0, $limit, true);
        } else {
            $limit = count($aryStatus);
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

            if (strtotime("{$datestart} {$timestart}") > requestTime() || strtotime("{$dateend} {$timeend}") < requestTime()) {
                continue;
            }

            $item = [];
            $type = $config->get('media_banner_type', '', $i);
            $source = $config->get('media_banner_source', '', $i);
            $target = $config->get('media_banner_target', '', $i);
            $mid = $config->get('media_banner_mid', '', $i);
            $attr1 = $config->get('media_banner_attr1', '', $i);
            $attr2 = $config->get('media_banner_attr2', '', $i);
            $alt = $config->get('media_banner_alt', '', $i);
            $link = $config->get('media_banner_link', '', $i);

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
        $SQL->addWhereIn('media_id', $mids);
        $q = $SQL->get(dsn());
        $row = Database::query($q, 'all');

        foreach ($items as $i => $item) {
            foreach ($row as $media) {
                if (isset($item['banner#img']) && $item['banner#img']['mid'] === $media['media_id']) {
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
        return $items;
    }
}
