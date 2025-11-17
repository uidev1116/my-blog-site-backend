<?php

use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Application;
use Exception;

class ACMS_CorrectorBody
{
    /**
     * @var array
     */
    public $const = [];

    public function jsonPrint($txt)
    {
        return json_encode($txt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    }

    /**
     * 改行文字をHTMLの<br />タグに変換
     *
     * @param string|null $txt
     * @return string
     */
    public function nl2br($txt): string
    {
        return nl2br($txt ?? '');
    }

    /**
     * 改行文字をHTMLの<br>タグに変換
     *
     * @param string|null $txt
     * @return string
     */
    public function nl2br4html($txt): string
    {
        return nl2br($txt ?? '', false);
    }

    public function delnl($txt)
    {
        return preg_replace("/(\xe2\x80[\xa8-\xa9]|\xc2\x85|\r\n|\r|\n)/", "", $txt);
    }

    public function escape($txt, $args = [])
    {
        if (!empty($args) and is_array($args)) {
            $rep = [
                '&' => '&amp;',
                '<' => '&lt;',
                '>' => '&gt;',
                '"' => '&quot;',
                "'" => '&#039;',
            ];
            foreach ($args as $val) {
                if (!isset($rep[$val])) {
                    continue;
                }
                unset($rep[$val]);
            }
            return str_replace(array_keys($rep), array_values($rep), $txt ?? '');
        } else {
            if (is_null($txt)) {
                $txt = '';
            }
            if (!is_string($txt)) {
                $txt = (string)json_encode($txt);
            }
            return htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
        }
    }

    public function allow_dangerous_tag($txt)
    {
        return $txt;
    }

    /**
     * 安全なHTMLを返す
     *
     * @param string $txt
     */
    public function safe_html($txt): string
    {
        return $this->htmlPurifier($txt);
    }

    /**
     * HTMLを洗浄する
     *
     * @param string $txt
     * @return string
     */
    public function htmlPurifier($txt): string
    {
        static $purifier;

        if (is_array($txt)) {
            $txt = implode($txt);
        }
        if (!$txt) {
            return strval($txt);
        }
        if ($purifier === null) {
            $purifier = Application::make('html-purifier');
        }
        return $purifier->clean($txt);
    }

    /**
     * 危険なスキームを検出して置換する
     *
     * @param mixed $txt
     * @return string
     */
    public function sanitizeScheme($txt): string
    {
        if (is_array($txt)) {
            $txt = implode($txt);
        }
        if (!is_string($txt)) {
            return strval($txt);
        }
        $blacklist = [
            'javascript', 'data', 'vbscript', 'file', 'blob', 'unsafe',
            'about', 'ftp', 'ws', 'wss', 'jar', 'mocha', 'view-source',
            'intent', 'ms-settings', 'shell', 'command', 'powershell',
            'chrome', 'chrome-extension', 'opera', 'opera-extension', 'edge', 'ms-browser-extension',
        ];
        $decoded = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = urldecode($decoded);
        $trimmed = trim($decoded);
        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false) {
            return $txt; // URLではない、相対URLはOK
        }
        if (in_array(strtolower($scheme), $blacklist, true)) {
            return '#'; // 危険なスキーマは完全に無効化
        }
        return $txt;
    }

    public function escvars($txt)
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], $txt);
    }

    public function escquot($txt)
    {
        return preg_replace('@"@', '""', $txt);
    }

    public function trim($txt, $args = [])
    {
        if (!isset($args[0])) {
            return $txt;
        }
        $width = intval($args[0]);
        $marker = isset($args[1]) ? $args[1] : '';

        return mb_strimwidth($txt, 0, $width, $marker);
    }

    public function mb_trim($txt, $args = [])
    {
        if (!isset($args[0])) {
            return $txt;
        }
        if (empty($txt)) {
            return $txt;
        }
        $width = intval($args[0]);
        $marker = isset($args[1]) ? $args[1] : '';

        if ($width < mb_strlen($txt)) {
            return mb_substr($txt, 0, $width) . $marker;
        }
        return $txt;
    }

    public function trim4ext($txt, $args = [])
    {
        if (!empty($args[0]) && is_array($args) && preg_match('@^.(.*)$@si', $args[0], $match)) {
            return str_replace($args[0], '', $txt);
        } else {
            return $txt;
        }
    }

    public function table($csv, $args = [])
    {
        if (empty($csv)) {
            return $csv;
        }
        $csv = preg_replace(['/&gt;(\d+)/', '/&quot;/'], ['>$1', '"'], $csv);

        //-----------
        // overwrite
        $i = 0;
        $m = [];
        foreach (
            [
                'column' => ',',
                'row' => '[\r\n]+',
                'enclosure' => '"',
                'head' => '#',
                'align' => '\|',
                'nowrapS' => '\[',
                'nowrapE' => '\]',
                'regex' => '@',
                'rspan' => '\^\d+',
                'cspan' => '\>\d+',
            ] as $key => $val
        ) {
            $m[$key] = !empty($args[$i]) ? $args[$i] : $val;
            $i++;
        }

        //--------
        // double
        $doubleCheck = array_unique($m);
        if (count($m) !== count($doubleCheck)) {
            return $csv;
        }

        //-----
        // ptn
        $ptn = $m['regex']
            . '(^|' . $m['column'] . '|' . $m['row'] . ')'
            . '((?:\t| |　|' . $m['rspan'] . '|' . $m['cspan'] . '|' . $m['head'] . '|' . $m['nowrapS'] . '|' . $m['align'] . ')*)'
            . '(?:(?:' . $m['enclosure'] . '((?:[^' . $m['enclosure'] . ']|' . $m['enclosure'] . $m['enclosure'] . ')*)' . $m['enclosure'] . ')|([^' . $m['column'] . ']*?))'
            . '(?=([[:blank:]' . $m['nowrapE'] . $m['align'] . ']*+)(?:' . $m['column'] . '|' . $m['row'] . '|$))'
            . $m['regex'] . 'u';

        preg_match_all($ptn, $csv, $matches, PREG_SET_ORDER);

        $html = '<tr>';

        while (!!($match = array_shift($matches))) {
            //------------------
            // 1 : delimiter
            // 2 : option left
            // 3 : cell escaped
            // 4 : cell
            // 5 : option right

            $delimiter = $match[1];
            $optionL = $match[2];
            $cell = $match[3] | $match[4];
            $optionR = !empty($match[5]) ? $match[5] : '';

            if (preg_match('@^' . $m['row'] . '$@', $delimiter)) {
                $html .= "</tr>\n<tr>";
            }

            $attr = '';

            //-------
            // align
            if (false !== strpos($optionL, '|')) {
                if (false !== strpos($optionR, '|')) {
                    $attr .= ' style="text-align:center"';
                } else {
                    $attr .= ' style="text-align:left"';
                }
            } else {
                if (false !== strpos($optionR, '|')) {
                    $attr .= ' style="text-align:right"';
                }
            }

            //-------
            // span
            if (preg_match('@\>(\d+)@', $optionL, $ncol)) {
                $col = intval($ncol[1]);
                $attr .= ' colspan="' . $col . '"';
            }
            if (preg_match('@\^(\d+)@', $optionL, $nrow)) {
                $row = intval($nrow[1]);
                $attr .= ' rowspan="' . $row . '"';
            }

            //--------
            // nowrap
            if (false !== strpos($optionL, '[') and false !== strpos($optionR, ']')) {
                $attr .= ' nowrap="nowrap"';
            }
            $optionL = '_' . $optionL . '_';
            $tag = (preg_match('/^_[^#]{0,}?#[^#]{0,}?_/', $optionL)) ? 'th' : 'td';
            $cell = preg_match('/^_[#]{2}?_/', $optionL) ? '#' . $cell : $cell;
            $html .= '<' . $tag . $attr . '>' . nl2br(str_replace('""', '"', $cell)) . '</' . $tag . '>';
        }
        $html .= "</tr>";

        return $html;
    }

    public function definition_list($txt, $args = [])
    {
        if ($lis = preg_split('@( |　|\t)*\r?\n@', $txt, -1, PREG_SPLIT_NO_EMPTY)) {
            $txt = "\n";
            foreach ($lis as $dval) {
                if (preg_match('/^#[^#]/', $dval)) {
                    $txt .= "<dt>" . preg_replace('@^#( |　|\t)*@', '', $dval) . "</dt>\n";
                } else {
                    $txt .= "<dd>" . preg_replace('/##/', '#', $dval) . "</dd>\n";
                }
            }
        }
        return $txt;
    }

    public function acms_corrector_list($txt)
    {
        if ($lis = preg_split('@( |　|\t)*\r?\n@', $txt, -1, PREG_SPLIT_NO_EMPTY)) {
            $txt = "\n<li>" . join("</li>\n<li>", $lis) . "</li>\n";
        }
        return $txt;
    }

    public function markdown($txt, $args = [])
    {
        $lv = intval(isset($args[0]) ? $args[0] : 0);
        if (0 < $lv) {
            $_txt = $txt;
            $txt = '';
            foreach (preg_split('@^@m', $_txt) as $token) {
                if ('#' == substr($token, 0, 1)) {
                    $token = str_repeat('#', $lv) . $token;
                }
                $txt .= $token;
            }
        }

        return Common::parseMarkdown($txt);
    }

    public function striptags($txt)
    {
        return strip_tags($txt);
    }

    public function urldecode($txt)
    {
        return urldecode($txt);
    }

    public function urlencode($txt)
    {
        // RFC3986
        return str_replace('%7E', '~', rawurlencode($txt));
    }

    public function html_entity_decode($txt)
    {
        return html_entity_decode($txt);
    }

    public function md5($txt)
    {
        return md5($txt);
    }

    public function base64($txt)
    {
        return base64_encode($txt);
    }

    public function number_format($txt)
    {
        if ($txt && is_numeric($txt)) {
            return number_format((float) $txt);
        } else {
            return $txt;
        }
    }

    public function str4script($txt)
    {
        return preg_replace(['@\'|"@', '@\r|\n@'], ['\\\$0', ''], $txt);
    }

    public function tax($txt, $args = [])
    {
        $args[0] = is_numeric($args[0]) ? $args[0] : intval($args[0]);
        return floor($txt * $args[0]);
    }

    public function convert($txt, $args = [])
    {
        return !empty($args[0]) ? mb_convert_kana($txt, strval($args[0])) : $txt;
    }

    public function camelcase_to_hyphen($txt)
    {
        return preg_replace("/([^_])([A-Z])/", "$1-$2", $txt);
    }

    public function symbolfont_path($txt)
    {
        if (
            in_array(
                $txt,
                [
                    'Blog_Field',
                    'Category_Field',
                    'Entry_Field',
                    'User_Field',
                    'Module_Field',
                ],
                true
            )
        ) {
            return 'entry_body';
        }

        return $this->lowercase($this->camelcase_to_hyphen($txt));
    }

    public function wareki($txt, $args = [])
    {
        $dt = strtotime($this->fixChars($txt));
        if (!$dt) {
            return $txt;
        }
        $ymd = date('Ymd', $dt);
        $y = (int) substr($ymd, 0, 4);

        $era = '';
        $year = null;
        if ($ymd <= '19120729') {
            $era = '明治';
            $year = $y - 1867;
        } elseif ($ymd >= '19120730' && $ymd <= '19261224') {
            $era = '大正';
            $year = $y - 1911;
        } elseif ($ymd >= '19261225' && $ymd <= '19890107') {
            $era = '昭和';
            $year = $y - 1925;
        } elseif ($ymd >= '19890108' && $ymd <= '20190431') {
            $era = '平成';
            $year = $y - 1988;
        } elseif ($ymd >= '20190501') {
            $era = '令和';
            $year = $y - 2018;
        }

        if ($era === '') {
            return $txt;
        }

        if (is_null($year)) {
            return $txt;
        }
        if (substr($year, 0, 1) == 0) {
            $year = preg_replace('@^0+@', '', $year);
        }
        $result = $era . $year;
        if (isset($args[0])) {
            $result .= $this->datetime($txt, $args);
        }
        return $result;
    }

    public function age($txt)
    {
        $dt = false !== ($dt = strtotime($this->fixChars($txt))) ? $dt : $txt;
        $ymd = date('Ymd', $dt);
        $txt = intval((date('Ymd') - strval($ymd)) / 10000);
        return $txt;
    }

    public function date($txt, $args = [])
    {
        return $this->datetime($txt, $args);
    }

    public function datetime($txt, $args = [])
    {
        if (!isset($args[0])) {
            return $txt;
        }
        $dt = strtotime($this->fixChars($txt));
        if (empty($dt)) {
            return $txt;
        }
        $txt = date($args[0], $dt);
        if ($txt === date($args[0], REQUEST_TIME) && isset($args[1])) {
            $txt = $args[1];
        }
        return $txt;
    }

    public function resizeImg($src, $args = [])
    {
        return $this->resizeImgBase($src, $args, ImageResize::SCALE_ASPECT_FILL);
    }

    public function resizeImgFit($src, $args = [])
    {
        return $this->resizeImgBase($src, $args, ImageResize::SCALE_ASPECT_FIT, true);
    }

    public function resizeImgFill($src, $args = [])
    {
        return $this->resizeImgBase($src, $args, ImageResize::SCALE_ASPECT_FILL, true);
    }

    public function resizeImgBase($src, $args, $mode, $stretch = false)
    {
        if (!isset($args[0])) {
            return $src;
        }
        if (!$src) {
            return '';
        }
        $src = urldecode($src);
        $parsedSrc = parse_url($src);
        $src = $parsedSrc['path'];
        $query = isset($parsedSrc['query']) ? '?' . $parsedSrc['query'] : '';
        $src = explode('?', $src, 2)[0];
        if (strpos($src, '..') !== false) {
            return ''; // 不正なパス
        }
        $hasLeadingSlash = isset($src[0]) && $src[0] === '/';
        if ($hasLeadingSlash) {
            $src = ltrim($src, '/'); // パスの先頭にスラッシュがあるとrealpathがfalseになるため、ltrimで削除
        }
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // 許可する拡張子
        $width = empty($args[0]) ? 0 : intval($args[0]);
        $height = (isset($args[1]) && !empty($args[1])) ? intval($args[1]) : 0;
        $color = isset($args[2]) ? strtolower($args[2]) : 'ffffff';
        $srcPath = $destPath = $destPathVars = '';

        $pfx = 'mode' . $mode . '_';
        if (!empty($width)) {
            $pfx .= 'w' . $width;
        }
        if (!empty($height)) {
            $pfx .= '_h' . $height;
        }
        if ($color !== 'ffffff') {
            $pfx .= '_' . $color;
        }

        foreach (['', MEDIA_LIBRARY_DIR, ARCHIVES_DIR] as $archive_dir) {
            $tmpPath = $archive_dir . normalSizeImagePath($src);
            $destPath = trim(dirname($tmpPath), '/') . '/' . $pfx . '-' . PublicStorage::mbBasename($tmpPath);
            $destPathVars = trim(dirname($src), '/') . '/' . $pfx . '-' . PublicStorage::mbBasename($tmpPath);
            $largePath = otherSizeImagePath($tmpPath, 'large'); // large path
            $realTmpPath = LocalStorage::safeRealpath($tmpPath);
            if (!$realTmpPath || strpos($realTmpPath, LocalStorage::safeRealpath(SCRIPT_DIR)) !== 0 || is_link($realTmpPath)) { // @phpstan-ignore-line
                continue;
            }
            $ext = strtolower(pathinfo($tmpPath, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                continue; // 不正な拡張子
            }
            if (PublicStorage::isReadable($destPath)) {
                return $hasLeadingSlash ? '/' . Media::urlencode($destPathVars) . $query : Media::urlencode($destPathVars) . $query;
            }
            if (strpos($largePath, MEDIA_LIBRARY_DIR) !== 0 && PublicStorage::isReadable($largePath)) {
                $srcPath = $largePath;
                break;
            }
            if (PublicStorage::isReadable($tmpPath)) {
                $srcPath = $tmpPath;
                break;
            }
        }
        if (empty($srcPath)) {
            return $hasLeadingSlash ? '/' . Media::urlencode($src) . $query : Media::urlencode($src) . $query;
        }
        if (!$xy = PublicStorage::getImageSize($srcPath)) {
            return $hasLeadingSlash ? '/' . Media::urlencode($src) . $query : Media::urlencode($src) . $query;
        }

        if (!$stretch) {
            if ($xy[0] < $width) {
                $width = $xy[0];
            }
            if ($xy[1] < $height) {
                $height = $xy[1];
            }
        }

        try {
            $image = new ImageResize($srcPath);
            $image->setMode($mode);
            $image->setBgColor($color);
            $image->setQuality(intval(config('resize_image_jpeg_quality', 75)));
            if (empty($width)) {
                $image->resizeToHeight($height);
            } else {
                if (empty($height)) {
                    $image->resizeToWidth($width);
                } else {
                    $image->resize($width, $height);
                }
            }
            $image->save($destPath);
            return $hasLeadingSlash ? '/' . Media::urlencode($destPathVars) . $query : Media::urlencode($destPathVars) . $query;
        } catch (Exception $e) {
            Logger::notice('画像リサイズに失敗しました', [
                'srcPath' => $srcPath,
                'destPath' => $destPath,
                'error' => $e->getMessage(),
            ]);
        }
        return $hasLeadingSlash ? '/' . Media::urlencode($srcPath) . $query : Media::urlencode($srcPath) . $query;
    }

    public function fixChars($txt)
    {
        $needle = ['年', '月', '日', '時', '分', '秒', '　'];
        $replacement = ['', '', '', '', '', '', ''];
        $txt = mb_convert_kana(str_replace($needle, $replacement, $txt), 'a');
        return $txt;
    }

    public function br4alnum($txt, $args = [])
    {
        if (
            0
            or !isset($args[0])
            or !($len = intval($args[0]))
        ) {
            return $txt;
        }
        $ptn = '@[[:alnum:]]{' . $len . '}(?=[[:alnum:]])@';
        $br = isset($args[1]) ? $args[1] : '<br />';

        $newText = '';
        while (preg_match($ptn, $txt, $match, PREG_OFFSET_CAPTURE)) {
            $pos = strlen($match[0][0]) + $match[0][1];
            $newText .= substr($txt, 0, $pos) . $br;
            $txt = substr($txt, $pos);
        }
        $newText .= $txt;

        return $newText;
    }

    public function weekEN2JP($txt, $args = [])
    {
        $en = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $jp = configArray('week_label');
        foreach ($en as $i => $val) {
            $txt = str_replace($val, $jp[$i], $txt);
        }
        return $txt;
    }

    public function basename($txt)
    {
        return LocalStorage::mbBasename($txt);
    }

    public function dirname($txt)
    {
        return dirname($txt);
    }

    public function zero_padding($txt, $args)
    {
        $length = isset($args[0]) ? intval($args[0]) : 4;
        return str_pad($txt, $length, '0', STR_PAD_LEFT);
    }

    public function convert_bytes($txt, $args)
    {
        if (!empty($args[0]) && preg_match('/^[gmkGMK]$/', $args[0])) {
            return convert_bytes($txt, $args[0], isset($args[1]) ? intval($args[1]) : 2);
        } else {
            return $txt;
        }
    }

    public function align2label($txt)
    {
        $dict = [
            'auto' => 'おまかせ',
            'center' => '中央',
            'right' => '右寄せ',
            'left' => '左寄せ',
        ];
        return isset($dict[$txt]) ? $dict[$txt] : $txt;
    }

    public function del_pictogram($txt)
    {
        if (!!($path = config('const_file_path')) && empty($this->const)) {
            include SCRIPT_DIR . $path;
            // @phpstan-ignore-next-line
            $this->const = $const;
        }
        return str_replace(array_keys($this->const), '', $txt);
    }

    public function split($txt, $args = [])
    {
        if (!isset($args[1])) {
            return $txt;
        }

        $count = intval($args[1]);
        $pattern = isset($args[0]) ? $args[0] : '';
        $data = preg_split('@' . $pattern . '@', $txt);
        if (!isset($data[$count])) {
            return $txt;
        }

        return $data[$count];
    }

    public function contrastColor($color, $args = [])
    {
        if (empty($color)) {
            return '';
        }
        $black = isset($args[0]) ? $args[0] : '#000000';
        $white = isset($args[1]) ? $args[1] : '#ffffff';

        return contrastColor($color, $black, $white);
    }

    public function validateUrl($url)
    {
        if (preg_match("/\Ahttps?:\/\//", $url) || preg_match("/\A\//", $url)) {
            return $url;
        } else {
            return "";
        }
    }

    public function lowercase($txt)
    {
        return strtolower($txt);
    }

    public function uppercase($txt)
    {
        return strtoupper($txt);
    }

    public function buildGlobalVars($txt)
    {
        return setGlobalVars($txt);
    }

    public function buildModule($txt)
    {
        return build($txt, Field_Validation::singleton('post'));
    }

    public function buildTpl($txt)
    {
        return $this->buildModule($this->buildGlobalVars($txt));
    }

    public function acmsRam($id, $args)
    {
        $method = isset($args[0]) ? $args[0] : false;
        if ($method) {
            $method = 'ACMS_RAM::' . $method;
            if (is_callable($method)) {
                return call_user_func($method, $id);
            } else {
                return $id;
            }
        }
        return $id;
    }

    public function getWidthFromRatio($ratio, $args = [])
    {
        $ratio = floatval($ratio);
        $height = isset($args[0]) ? intval($args[0]) : 0;
        if (empty($ratio) || empty($height)) {
            return '';
        }
        return round($height * $ratio);
    }

    public function getHeightFromRatio($ratio, $args = [])
    {
        $ratio = floatval($ratio);
        $width = isset($args[0]) ? intval($args[0]) : 0;
        if (empty($ratio) || empty($width)) {
            return '';
        }
        return round($width / $ratio);
    }

    public function imageRatioSizeH($src, $args = [])
    {
        foreach (['', ARCHIVES_DIR, MEDIA_LIBRARY_DIR] as $dir) {
            $size = PublicStorage::getImageSize($dir . urldecode($src));

            if ($size) {
                $width = isset($args[0]) ? intval($args[0]) : 200;
                list($x, $y) = $size;

                return intval($width * ($y / $x));
            }
        }
        return '';
    }

    public function imageRatioSizeW($src, $args = [])
    {
        foreach (['', ARCHIVES_DIR, MEDIA_LIBRARY_DIR] as $dir) {
            $size = PublicStorage::getImageSize($dir . urldecode($src));

            if ($size) {
                $height = isset($args[0]) ? intval($args[0]) : 200;
                list($x, $y) = $size;

                return intval($height * ($x / $y));
            }
        }
        return '';
    }

    public function jsonEscape($txt)
    {
        $escapeTxt = (string)json_encode($txt);
        return mb_substr($escapeTxt, 1, mb_strlen($escapeTxt) - 2);
    }

    public function substring($txt, $args = [])
    {
        $start = isset($args[0]) ? $args[0] : 0;
        $length = isset($args[1]) ? $args[1] : false;

        if ($length === false) {
            return mb_substr($txt, $start);
        }
        return mb_substr($txt, $start, $length);
    }

    public function entryStatusLabel($eid)
    {
        $entry = ACMS_RAM::entry($eid);
        if (empty($entry)) {
            return '';
        }
        $status = $entry['entry_status'];
        $stime = $entry['entry_start_datetime'];
        $etime = $entry['entry_end_datetime'];
        $approval = $entry['entry_approval'];
        $txt = '';

        switch ($status) {
            case 'close':
                $txt = config('admin_entry_title_prefix_close');
                break;
            case 'draft':
                $txt = config('admin_entry_title_prefix_draft');
                break;
            case 'trash':
                if (defined('RVID') && RVID) {
                    $txt = config('admin_entry_title_prefix_trash_approval');
                } else {
                    $txt = config('admin_entry_title_prefix_trash');
                }
                break;
        }
        if ($approval === 'pre_approval') {
            $txt = config('admin_entry_title_prefix_pre_approval');
        }
        if ($stime > date('Y-m-d H:i:s', requestTime())) {
            $txt = config('admin_entry_title_prefix_start');
        }
        if ($etime < date('Y-m-d H:i:s', requestTime())) {
            $txt = config('admin_entry_title_prefix_end');
        }
        return $txt;
    }

    /**
     * 指定された値と一致した場合、selected 属性を返す
     *
     * @param mixed $value
     * @param array $args
     * @return string
     */
    public function selected($value, $args = []): string
    {
        $expected = isset($args[0]) ? $args[0] : '';
        if (is_array($value)) {
            return in_array($expected, $value, true) ? 'checked' : '';
        }
        return $value === $expected ? 'selected' : '';
    }

    /**
     * 指定された値と一致した場合、checked 属性を返す
     *
     * @param mixed $value
     * @param array $args
     * @return string
     */
    public function checked($value, $args = []): string
    {
        $expected = isset($args[0]) ? $args[0] : '';
        if (is_array($value)) {
            return in_array($expected, $value, true) ? 'checked' : '';
        }
        return $value === $expected ? 'checked' : '';
    }
}
