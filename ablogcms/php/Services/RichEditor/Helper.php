<?php

namespace Acms\Services\RichEditor;

use Acms\Services\Facades\Common;

class Helper
{
    /**
     * リッチエディタのHTMLをレンダリング
     *
     * @param mixed $value
     * @return string
     */
    public function render($value)
    {
        if (is_string($value)) {
            $value = json_decode($value);
            if ($value && property_exists($value, 'html') && $value->html) {
                return $this->fix($value->html);
            }
        }
        return $value;
    }

    /**
     * リッチエディタのタイトルをレンダリング
     *
     * @param mixed $value
     * @return string
     */
    public function renderTitle($value)
    {
        if (is_string($value)) {
            $value = json_decode($value);
            if ($value && property_exists($value, 'title') && $value->title) {
                return $value->title;
            }
        }
        return "";
    }

    private function getAttributeMap($attributes, $values)
    {
        $map = [];
        foreach ($attributes as $i => $attribute) {
            //コーテーションを削除
            $map[$attribute] = preg_replace('/[\'\"]/', '', $values[$i]);
        }
        return $map;
    }

    private function getTagFromAttributeMap($map)
    {
        $img = "<img ";
        foreach ($map as $key => $value) {
            $img .= "$key=\"$value\" ";
        }
        $img .= ">";
        return $img;
    }

    /**
     * リッチエディタの内容を修正
     *
     * @param string $value
     * @return string
     */
    public function fix($value)
    {
        $value = preg_replace_callback('/<img(.*?)>/', function ($match) {
            $attrs = [];
            preg_match_all('/(\S+)=[\"\']?((?:.(?![\"\']?\s+(?:\S+)=|[>\"\']))+.)[\"\']?/', $match[1], $attrs);
            $attributes = $attrs[1];
            $values = $attrs[2];
            $map = $this->getAttributeMap($attributes, $values);
            if (empty($map["data-media_id"])) {
                return $match[0];
            }
            $mid = $map["data-media_id"];
            $media = loadMedia($mid);
            $path = '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $media->get('path');
            $map["src"] = $path;
            return $this->getTagFromAttributeMap($map);
        }, $value);

        if (!is_string($value)) {
            return '';
        }

        $value = Common::replaceDeliveryUrlAll($value);

        return $value;
    }
}
