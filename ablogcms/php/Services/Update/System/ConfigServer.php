<?php

namespace Acms\Services\Update\System;

use Acms\Services\Facades\LocalStorage;

class ConfigServer
{
    public function update($path)
    {
        $config = $this->build();
        LocalStorage::put($path, $config);
    }

    protected function build()
    {
        $definition = $this->getDefinition();
        $ignore = configArray('system_update_ignore_config');

        $EOL = "\r\n";
        $string = '<?php' . $EOL . $EOL;

        foreach ($definition as $def => $val) {
            if (in_array($def, $ignore, true)) {
                continue;
            }
            if ($val === 'BR') {
                $string .= $EOL;
            } elseif (preg_match('/^COMMENT_\d/', $def)) {
                $string .= '// ' . $val . $EOL;
            } else {
                if (!defined($def)) {
                    define($def, $val); // use default
                }
                $const = constant($def); // get constant
                if (is_bool($const)) {
                    $const = $const ? 1 : 0;
                } elseif ($def === 'CHMOD_DIR' || $def === 'CHMOD_FILE') {
                    $const = $val;
                } else {
                    $const = is_string($const) ? "'$const'" : $const; // fix strings
                    $const = $const === null ? 'null' : $const; // fix null
                }
                $string .= "define('$def', $const);" . $EOL; // add row
            }
        }

        return $string;
    }

    protected function getDefinition()
    {
        return [
            'DOMAIN' => '',
            'DOMAIN_BASE' => '',
            'DB_BR' => 'BR',
            'DB_TYPE' => 'mysql',
            'DB_HOST' => '',
            'DB_NAME' => '',
            'DB_USER' => '',
            'DB_PASS' => '',
            'DB_PORT' => null,
            'DB_CHARSET' => 'UTF-8',
            'DB_CONNECTION_CHARSET' => null,
            'DB_PREFIX' => '',
            'DB_SLOW_QUERY_TIME' => 0.2,
            'GETTEXT_BR' => 'BR',
            'COMMENT_1' => 'GETTEXT_TYPE: fix|user|auto',
            'GETTEXT_TYPE' => 'user',
            'COMMENT_2' => 'GETTEXT_APPLICATION_RANGE: admin|login|all',
            'GETTEXT_APPLICATION_RANGE' => 'all',
            'GETTEXT_DEFAULT_LOCALE' => 'ja_JP.UTF-8',
            'GETTEXT_DOMAIN' => 'messages',
            'GETTEXT_PATH' => 'lang',
            'PROXY_BR' => 'BR',
            'COMMENT_3' => 'プロキシが入っている場合、X-Forwarded-ForヘッダーからクライアントIPアドレスを特定するため、',
            'COMMENT_4' => '信頼できるプロキシのIPを設定します。 例: xxx.xxx.xxx.xxx,yyy.yyy.yyy.yyy',
            'TRUSTED_PROXY_LIST' => '',
            'PROXY_IP_HEADER' => 'HTTP_X_FORWARDED_FOR',
            'COMMENT_5' => 'オンラインアップデート、Webhook、SNSログイン（LINE）のみ対応',
            'PROXY_PORT' => '',
            'PROXY_IP' => '',
            'CHMOD_BR' => 'BR',
            'COMMENT_6' => 'CMSで作成するディレクトリ・ファイルのパーミッションを設定します',
            'CHMOD_DIR' => '(0775 & ~ umask())',
            'CHMOD_FILE' => '(0664 & ~ umask())',
            'SSL_BR' => 'BR',
            'SSL_ENABLE' => 0,
            'FULLTIME_SSL_ENABLE' => 0,
            'COOKIE_SECURE' => 0,
            'COOKIE_HTTPONLY' => 1,
            'COOKIE_SAME_SITE' => 'Lax',
            'HOOK_ENABLE' => 0,
            'RESOLVE_PATH' => 1,
            'URL_SUFFIX_SLASH' => 1,
            'SESSION_NAME' => 'sid',
            'ACMS_HASH_NAME' => 'acms_hash',
            'REWRITE_FORCE' => 1,
            'MAX_PUBLISHES' => 3,
            'MAX_EXECUTION_TIME' => 30,
            'DEFAULT_TIMEZONE' => 'Asia/Tokyo',
            'DOCUMENT_ROOT_FORCE' => null,
            'PHP_SESSION_USE_DB' => 0,
            'DIR_BR' => 'BR',
            'THEMES_DIR' => 'themes/',
            'ARCHIVES_DIR' => 'archives/',
            'MEDIA_LIBRARY_DIR' => 'media/',
            'MEDIA_STORAGE_DIR' => 'storage/',
            'CACHE_DIR' => 'cache/',
            'ARCHIVES_CACHE_SERVER' => '',
            'PHP_DIR' => 'php/',
            'JS_DIR' => 'js/',
            'IMAGES_DIR' => 'images/',
            'PATH_BR' => 'BR',
            'CONFIG_FILE' => 'private/config.system.yaml',
            'CONFIG_DEFAULT_FILE' => 'private/config.system.default.yaml',
            'MIME_TYPES_FILE' => 'private/mime.types',
            'REWRITE_PATH_EXTENSION' => 'pdf|doc|docx|ppt|pptx|xls|xlsx|lzh|zip|rar',
            'ERROR_LOG_FILE' => '',
            'ASYNC_PROCESS_LOG_PATH' => '',
            'COMMENT_7' => '非同期処理でPHPパスが合わない場合に使用。例1: PHP_BINDIR . \'/php -c /path/to/php.ini\' 例2: \'C:\xampp\php\php.exe\'',
            'PHP_PROCESS_BINARY' => '',
            'SEGMENT_BR' => 'BR',
            'BID_SEGMENT' => 'bid',
            'AID_SEGMENT' => 'aid',
            'UID_SEGMENT' => 'uid',
            'CID_SEGMENT' => 'cid',
            'EID_SEGMENT' => 'eid',
            'UTID_SEGMENT' => 'utid',
            'CMID_SEGMENT' => 'cmid',
            'KEYWORD_SEGMENT' => 'keyword',
            'TAG_SEGMENT' => 'tag',
            'FIELD_SEGMENT' => 'field',
            'ORDER_SEGMENT' => 'order',
            'ALT_SEGMENT' => 'alt',
            'TPL_SEGMENT' => 'tpl',
            'PAGE_SEGMENT' => 'page',
            'PROXY_SEGMENT' => 'proxy',
            'SPAN_SEGMENT' => '-',
            'ADMIN_SEGMENT' => 'admin',
            'MEDIA_FILE_SEGMENT' => 'media-download',
            'LOGIN_SEGMENT' => 'login',
            'ADMIN_RESET_PASSWORD_SEGMENT' => 'admin-reset-password',
            'ADMIN_RESET_PASSWORD_AUTH_SEGMENT' => 'admin-reset-password-auth',
            'ADMIN_TFA_RECOVERY_SEGMENT' => 'admin-tfa-recovery',
            'SEGMENT2_BR' => 'BR',
            'SIGNIN_SEGMENT' => 'signin',
            'SIGNUP_SEGMENT' => 'signup',
            'RESET_PASSWORD_SEGMENT' => 'reset-password',
            'RESET_PASSWORD_AUTH_SEGMENT' => 'reset-password-auth',
            'TFA_RECOVERY_SEGMENT' => 'tfa-recovery',
            'PROFILE_UPDATE_SEGMENT' => 'mypage/update-profile',
            'PASSWORD_UPDATE_SEGMENT' => 'mypage/update-password',
            'EMAIL_UPDATE_SEGMENT' => 'mypage/update-email',
            'TFA_UPDATE_SEGMENT' => 'mypage/update-tfa',
            'WITHDRAWAL_SEGMENT' => 'mypage/withdrawal',
            'SEGMENT3_BR' => 'BR',
            'LIMIT_SEGMENT' => 'limit',
            'DOMAIN_SEGMENT' => 'domain',
            'API_SEGMENT' => 'api',
            'IOS_APP_UA' => 'acms_iOS_app',
            'ETC_BR' => 'BR',
            'COMMENT_8' => '本番運用時に DEBUG_MODE を必ず 0 に設定して下さい',
            'DEBUG_MODE' => 0,
            'BENCHMARK_MODE' => 0
        ];
    }
}
