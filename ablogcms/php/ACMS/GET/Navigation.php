<?php

use Acms\Services\Facades\Http;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;

class ACMS_GET_Navigation extends ACMS_GET
{
    public $parentNavi = [];

    function get()
    {
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        if (!$labels = configArray('navigation_label')) {
            return '';
        }

        $Parent     = [];
        $notPublish = [];
        $levelLabel = configArray('navigation_ul_level');

        foreach ($labels as $i => $label) {
            $id     = $i + 1;

            $pid    = intval(config('navigation_parent', 0, $i));
            if (config('navigation_publish', null, $i) === 'on') {
                $Parent[$pid][$id]  = [
                    'id'        => $id,
                    'pid'       => $pid,
                    'label'     => $label,
                    'uri'       => config('navigation_uri', null, $i),
                    'target'    => config('navigation_target', null, $i),
                    'attr'      => config('navigation_attr', null, $i),
                    'a_attr'    => config('navigation_a_attr', null, $i),
                    'end'       => [],
                ];
            } else {
                $notPublic[] = $id;
            }
            $this->parentNavi[$id] = $pid;
        }

        if (count($Parent) === 0) {
            return $Tpl->get();
        }

        foreach ($notPublish as $nid) {
            foreach ($Parent[$nid] as & $obj) {
                unset($obj);
            }
        }

        $all        = [];
        $pidStack   = [0];
        while (count($pidStack)) {
            $pid    = array_pop($pidStack);
            /** @phpstan-ignore-next-line */
            while (isset($Parent[$pid]) && $row = array_shift($Parent[$pid])) {
                $id = $row['id'];
                $row['end'][]   = 'li#rear';
                $all[] = $row;
                if (isset($Parent[$id])) {
                    $pidStack[] = $pid;
                    $pidStack[] = $id;
                    break;
                }
            }
            if (!empty($row)) {
                $row    = array_pop($all);
                $row['end']   = ['ul#front'];
                $all[] = $row;
            } elseif (!empty($pidStack)) {
                $row    = array_pop($all);
                $row['end'][]   = 'ul#rear';
                $row['end'][]   = 'li#rear';
                $all[] = $row;
            }
        }

        $lvLabel    = isset($levelLabel[0]) ? $levelLabel[0] : '1';
        $Tpl->add('ul#front', ['ulLevel' => $lvLabel]);
        foreach ($all as $navigation) {
            $uri        = $navigation['uri'];
            $label      = $navigation['label'];

            if (!preg_match('/^#$/', $uri)) {
                $acmsPath   = preg_replace('@^acms://@', '', $uri);
                if ($uri <> $acmsPath) {
                    $Q      = parseAcmsPath($acmsPath);
                    $rep    = [];

                    if (!$Q->isNull('bid')) {
                        $rep['%{BLOG_NAME}']    = ACMS_RAM::blogName($Q->get('bid'));
                    }
                    if (!$Q->isNull('cid')) {
                        $rep['%{CATEGORY_NAME}']    = ACMS_RAM::categoryName($Q->get('cid'));
                    }
                    if (!$Q->isNull('eid')) {
                        $rep['%{ENTRY_TITLE}']  = ACMS_RAM::entryTitle($Q->get('eid'));
                    }

                    $label  = str_replace(array_keys($rep), array_values($rep), $label);

                    $uri    = acmsLink($Q, false);
                } else {
                    //$uri    = setGlobalVars($uri);
                    $label  = setGlobalVars($label);
                }
                $_target    = $navigation['target'];
                $lvBlock    = 'level_' . strval($this->buildLevel(intval($navigation['id'])));
                $Tpl->add([$lvBlock, 'link#front', 'navigation:loop']);
                if (in_array('ul#front', $navigation['end'], true)) {
                    $Tpl->add(['childNavi', 'link#front', 'navigation:loop']);
                }
                $Tpl->add(['link#front', 'navigation:loop'], [
                    'url'       => $uri,
                    'target'    => $_target,
                    'attr'  => (substr($navigation['a_attr'], 0, 1) !== ' ' ? ' ' : '') . $navigation['a_attr'],
                ]);
                $Tpl->add(['link#rear', 'navigation:loop']);
            }

            $scheme = parse_url($label, PHP_URL_SCHEME);
            if ($scheme === 'acms') {
                if (!preg_match('@^ablogcms@', UA)) { // against double load
                    $Q = parseAcmsPath(preg_replace('@^acms://@', '', $label));
                    $url = acmsLink($Q, false);
                    $label = '';
                    try {
                        if ($url === '' || $url === false) {
                            throw new RuntimeException('URL is empty');
                        }
                        $req = Http::init($url, 'GET');
                        $req->setRequestHeaders([
                            'User-Agent: ' . 'ablogcms/' . VERSION,
                            'Accept-Language: ' . HTTP_ACCEPT_LANGUAGE,
                        ]);
                        $response = $req->send();
                        if (strpos(Http::getResponseHeader('http_code'), '200') === false) {
                            throw new RuntimeException(Http::getResponseHeader('http_code'));
                        }
                        $label = $response->getResponseBody();
                    } catch (Exception $e) {
                        Logger::warning('ナビゲーションモジュール: HTTPインクルードできませんでした', Common::exceptionArray($e, ['url' => $url]));
                    }
                } else {
                    $label  = '';
                }
            }

            $level      = $this->buildLevel(intval($navigation['id']));
            $lvLabel    = isset($levelLabel[$level]) ? $levelLabel[$level] : strval($level);
            $lvBlock    = 'level_' . strval($level);

            $vars   = [
                'label' => $label,
                'level' => strval($this->buildLevel(intval($navigation['id']))),
            ];
            if (!preg_match('/^#$/', $uri)) {
                $vars['attr']   = (substr($navigation['attr'], 0, 1) !== ' ' ? ' ' : '') . $navigation['attr'];
            } else {
                $Tpl->add(['li#front', 'navigation:loop']);
            }

            $Tpl->add([$lvBlock, 'navigation:loop']);
            $Tpl->add('navigation:loop', $vars);

            foreach ($navigation['end'] as $block) {
                if ($block === 'ul#front') {
                    $Tpl->add([$lvBlock, 'ul#front', 'navigation:loop']);
                    $Tpl->add(['ul#front', 'navigation:loop'], [
                        'ulLevel'   => $lvLabel,
                    ]);
                } else {
                    $Tpl->add([$lvBlock, $block, 'navigation:loop']);
                    $Tpl->add([$block, 'navigation:loop']);
                }
                $Tpl->add('navigation:loop');
            }
        }
        $Tpl->add(['ul#rear', 'navigation:loop']);
        $Tpl->add('navigation:loop');

        return setGlobalVars($Tpl->get());
    }

    function buildLevel($id, $recursive = false)
    {
        static $level = 1;
        if (!$recursive) {
            $level = 1;
        }

        $pid = intval($this->parentNavi[$id]);
        if ($pid === 0) {
            return $level;
        }
        $level++;
        return $this->buildLevel($pid, true);
    }
}
