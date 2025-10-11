<?php

use Twig\Environment;

class ACMS_GET_Admin_Dashboard_PhpStatus extends ACMS_GET
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            return '';
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        // ディレクティブ
        $directive = [
            'memory_limit',
            'upload_max_filesize',
            'post_max_size',
            'max_file_uploads',
            'safe_mode',
            'max_input_vars'
        ];

        foreach ($directive as $key) {
            $val = ini_get($key);
            if (empty($val)) {
                continue;
            }
            $ini[$key] = $val;
        }

        $ini['ImageRotate'] = function_exists('imagerotate') ? '使用可' : '不可';
        $ini['php_version'] = PHP_VERSION;
        $ini['mysql_version'] = DB::getVersion();
        $ini['twig_version'] = Environment::VERSION;
        $ini['php_datetime'] = date('Y-m-d H:i:s');
        $ini['php_gettext'] = (
            function_exists('gettext') &&
            function_exists('bindtextdomain') &&
            function_exists('textdomain')
        ) ? 'enable' : 'disable';
        $ini['php_imagick'] = class_exists('Imagick') ? 'enable' : 'disable';

        if (strpos(PHP_SAPI, 'apache') !== false) {
            $ini['php_sapi'] = 'モジュール版';
        } elseif (strpos(PHP_SAPI, 'cgi') !== false) {
            $ini['php_sapi'] = 'CGI版';
        } else {
            $ini['php_sapi'] = '不明';
        }

        $Tpl->add(null, $ini);

        return $Tpl->get();
    }
}
