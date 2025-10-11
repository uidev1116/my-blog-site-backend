<?php

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Config;

class ACMS_GET_Admin_CheckList extends ACMS_GET
{
    public function get()
    {
        if (!sessionWithSubscription()) {
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $DB     = DB::singleton(dsn());
        $keyword = $this->Get->get('keyword');

        if (!empty($keyword)) {
            $SQL = SQL::newSelect('config', 'config');
            $SQL->addLeftJoin('config_set', 'config_set_id', 'config_set_id', 'config_set', 'config');
            $SQL->addLeftJoin('module', 'config_module_id', 'module_id');
            $SQL->addLeftJoin('rule', 'config_rule_id', 'rule_id');
            $SQL->addWhereOpr('config_key', '%' . $keyword . '%', 'LIKE', 'OR');
            $SQL->addWhereOpr('config_value', '%' . $keyword . '%', 'LIKE', 'OR');
            $SQL->setLimit(300);
            $configAll = $DB->query($SQL->get(dsn()), 'all');

            if (is_array($configAll) && count($configAll) > 0) {
                foreach ($configAll as $config) {
                    $bid = $config['config_blog_id'];
                    $setId = $config['config_set_id'];
                    $rid = $config['config_rule_id'];
                    $mid = $config['config_module_id'];
                    $configVars = [
                        'bid'   => $bid,
                        'setId' => $setId,
                        'rid'   => $rid,
                        'mid'   => $mid,
                        'configSetName' => $config['config_set_name'],
                        'rcode' => $config['rule_name'],
                        'mcode' => $config['module_identifier'],
                        'key'   => $config['config_key'],
                        'value' => $config['config_value'],
                    ];
                    $configVars['blogUrl'] = acmsLink([
                        'bid' => $bid,
                        'admin' => 'config_index',
                    ]);
                    $configVars['configSetUrl'] = acmsLink([
                        'bid' => $bid,
                        'query' => [
                            'setid' => $setId,
                        ],
                        'admin' => 'config_index',
                    ]);
                    $configVars['ruleUrl'] = acmsLink([
                        'bid' => $bid,
                        'query' => [
                            'setid' => $setId,
                            'rid' => $rid,
                        ],
                        'admin' => 'config_index',
                    ]);
                    $configVars['moduleUrl']  = acmsLink([
                        'bid' => $bid,
                        'query' => [
                            'rid' => $rid,
                            'mid' => $mid,
                        ],
                        'admin' => 'module_edit',
                    ]);
                    $Tpl->add(['config:loop', 'config'], $configVars);
                }
            } else {
                $Tpl->add(['notFound', 'config']);
            }
            $Tpl->add('config');
        }

        if (LICENSE_BLOG_LIMIT == 2147483647) {
            $Tpl->add(['userUnlimited', 'license']);
        } else {
            $Tpl->add(['userLimited', 'license'], [
                'limit' => LICENSE_BLOG_LIMIT,
            ]);
        }

        //-------------
        // debug mode
        $debugMode['mode'] = isDebugMode() ? 'ON' : 'OFF';
        if (isDebugMode()) {
            $debugMode['caution'] = 'caution';
        }
        $Tpl->add('debugMode', $debugMode);

        //-------------
        // debug mode
        $benchmarkMode['mode'] = isBenchMarkMode() ? 'ON' : 'OFF';
        if (isBenchMarkMode()) {
            $benchmarkMode['caution'] = 'caution';
        }
        $Tpl->add('benchmarkMode', $benchmarkMode);

        //------------
        // 画像エンジン
        if (class_exists('Imagick') && config('image_magick') == 'on') {
            $Tpl->add('imgLibrary', [
                'mode'  => 'ImageMagick',
            ]);
        } else {
            $Tpl->add('imgLibrary', [
                'mode'  => 'GD',
            ]);
        }

        //------------
        // WebPサポート
        $imageEngine =  Application::make('image.engine');
        if ($imageEngine->isWebpSupported()) {
            $Tpl->add('webpSupport');
            if (config('convert_2webp') === 'on') {
                $Tpl->add('webpAutoConvert');
            } else {
                $Tpl->add('webpNotAutoConvert');
            }
        } else {
            $Tpl->add('webpNotSupport');
        }

        //------------
        // ロスレス圧縮
        $Tpl->add('imgOptimizer', [
            'format' => implode(', ', $this->imgOptimizerCheck()),
        ]);

        //-------
        // cache
        $SQL = SQL::newSelect('blog');
        $SQL->setOrder('blog_id');

        foreach ($DB->query($SQL->get(dsn()), 'all') as $blog) {
            $bid = $blog['blog_id'];
            $this->addBlogInfo($Tpl, $bid);

            $SQL = SQL::newSelect('rule');
            $SQL->addSelect('rule_id');
            $SQL->addWhereOpr('rule_blog_id', $bid);
            foreach ($DB->query($SQL->get(dsn()), 'all') as $rule) {
                $rid = $rule['rule_id'];
                $this->addBlogInfo($Tpl, $bid, $rid);
            }
        }

        //------
        // form
        $SQL = SQL::newSelect('form');
        $SQL->addOrder('form_blog_id');
        $SQL->addOrder('form_id');
        $formAll = $DB->query($SQL->get(dsn()), 'all');

        if (is_array($formAll)) {
            foreach ($formAll as $form) {
                $formField = acmsDangerUnserialize($form['form_data']);
                if (!($formField instanceof Field)) {
                    continue;
                }
                $formVars = $this->buildField($formField, $Tpl, ['formGeneral:loop']);
                $formVars['bid']    = $form['form_blog_id'];
                $formVars['fmid']   = $form['form_id'];
                $formVars['editUrl']  = acmsLink([
                    'bid'       => $form['form_blog_id'],
                    'query'     => [
                        'fmid'   => $form['form_id'],
                    ],
                    'admin'     => 'form_edit',
                ]);

                $Tpl->add('formGeneral:loop', $formVars);

                $formVars = $this->buildField($formField, $Tpl, ['formAdmin:loop']);
                $formVars['bid']    = $form['form_blog_id'];
                $formVars['fmid']   = $form['form_id'];
                $formVars['editUrl']  = acmsLink([
                    'bid'       => $form['form_blog_id'],
                    'query'     => [
                        'fmid'   => $form['form_id'],
                    ],
                    'admin'     => 'form_edit',
                ]);
                $Tpl->add('formAdmin:loop', $formVars);
            }
        }
        if (!empty($keyword)) {
            $Tpl->add(null, [
                'keyword'   => $keyword,
            ]);
        }

        return $Tpl->get();
    }

    function addBlogInfo(&$Tpl, $bid = 0, $rid = null)
    {
        $configSetId = null;
        if ($setId = ACMS_RAM::blogConfigSetId($bid)) {
            $configSetId = $setId;
        }
        if ($configSetId === null) {
            if ($setId = Config::getAncestorBlogConfigSet($bid, 'config')) {
                $configSetId = $setId;
            }
        }

        $config = new Field();
        $config->overload(Config::loadDefaultField());
        $config->overload(Config::loadBlogConfigSet($bid));

        if ($rid && $rid > 0) {
            if ($ruleConfigSet = Config::loadRuleConfigSet($rid)) {
                $config->overload($ruleConfigSet);
            }
        }

        $blogConfig = [
            'bid' => $bid,
            'blogName' => ACMS_RAM::blogName($bid),
            'setid' => $configSetId,
            'setName' => ACMS_RAM::configSetName($configSetId),
            'rid' => $rid,
            'ruleName' => ACMS_RAM::ruleName($rid),
            'cache' => $config->get('cache'),
            'cacheCaution' => $config->get('cache') === 'off' ? 'caution' : '',
            'cacheClearWhenPost' => $config->get('cache_clear_when_post'),
            'subscriberCache' => $config->get('subscriber_cache'),
            'cacheClearTarget' => $config->get('cache_clear_target'),
            'clientCache' => $config->get('cache_expire_client'),

        ];
        $blogConfig['editUrl'] = acmsLink([
            'bid' => $bid,
            'query' => [
                'setid' => $setId,
                'rid' => $rid,
            ],
            'admin' => 'config_cache',
        ]);
        $Tpl->add('blog:loop', $blogConfig);
    }

    function imgOptimizerCheck()
    {
        $format = [];
        if (
            0
            || !LocalStorage::isWritable(THEMES_DIR . 'system/images/system/check.jpeg')
            || !LocalStorage::isWritable(THEMES_DIR . 'system/images/system/check.png')
            || !LocalStorage::isWritable(THEMES_DIR . 'system/images/system/check.gif')
        ) {
            $format[] = 'Permission denied';
            return  $format;
        }
        $optimizer =  Application::make('image.optimizer');

        if ($optimizer->optimizeTest(THEMES_DIR . 'system/images/system/check.jpeg')) {
            $format[] = 'jpeg';
        }
        if ($optimizer->optimizeTest(THEMES_DIR . 'system/images/system/check.png')) {
            $format[] = 'png';
        }
        if ($optimizer->optimizeTest(THEMES_DIR . 'system/images/system/check.gif')) {
            $format[] = 'gif';
        }
        if ($optimizer->optimizeTest(THEMES_DIR . 'system/images/system/check.webp')) {
            $format[] = 'webp';
        }
        return $format;
    }

    function config($key = null, $bid = 1, $rid = null)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('config');
        $SQL->addSelect('config_value');
        $SQL->addWhereOpr('config_blog_id', $bid);
        $SQL->addWhereOpr('config_key', $key);
        $SQL->addWhereOpr('config_rule_id', $rid);

        if ($config = $DB->query($SQL->get(dsn()), 'one')) {
            return $config;
        } else {
            $config = loadDefaultConfig();
            if (isset($config[$key])) {
                return $config[$key];
            }
        }
        return false;
    }
}
