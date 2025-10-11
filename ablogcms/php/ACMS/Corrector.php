<?php

use Acms\Services\Common\CorrectorFactory;

/**
 * @mixin \ACMS_CorrectorBody
 */
class ACMS_Corrector
{
    /**
     * @var Acms\Services\Common\CorrectorFactory
     */
    protected $factory;

    /**
     * ACMS_Corrector constructor.
     */
    public function __construct()
    {
        $this->factory = CorrectorFactory::singleton();
    }

    public function __call($method, $argument)
    {
        return $this->factory->call($method, '', $argument, true);
    }

    /**
     * @param string $txt
     * @param string $opt
     * @param string $name
     * @return string
     */
    public function correct($txt, $opt, $name)
    {
        if (
            'selected' == $name ||
            'checked' == $name ||
            'disabled' == $name ||
            'class' == $name ||
            'attr' == $name ||
            is_int(strpos($name, ':selected')) ||
            is_int(strpos($name, ':checked'))
        ) {
            // 特殊な名前の変数は強制的にraw校正オプションを付与
            if (empty($opt)) {
                $opt = 'raw';
            } elseif (
                strpos($opt, 'escape') === false &&
                strpos($opt, 'raw') === false
            ) {
                $opt = 'raw|' . $opt;
            }
        }

        // デフォルトで、escapeオプションを付与
        if (empty($opt)) {
            $opt = 'escape';
        } elseif (
            strpos($opt, 'escape') === false &&
            strpos($opt, 'raw') === false &&
            strpos($opt, 'resizeImg') === false
        ) {
            $opt = 'escape|' . $opt;
        }

        // デフォルトで、sanitizeSchemeオプションを付与
        if (config('sanitize_scheme') !== 'off' && strpos($opt, 'sanitizeScheme') === false && strpos($opt, 'allow_dangerous_scheme') === false) {
            $opt = 'sanitizeScheme|' . $opt;
        }

        // [raw]校正オプションがあり、かつ[allow_dangerous_tag]校正オプションが指定されてなければ、危険なタグを削除する校正オプションを付与
        if (
            config('strip_dangerous_tag') === 'on' &&
            strpos($opt, 'allow_dangerous_tag') === false &&
            strpos($opt, 'raw') !== false
        ) {
            $opt = "htmlPurifier|{$opt}";
        }

        // 校正オプションをコール
        $opt = '|' . $opt;
        while (preg_match('@\s*\|\s*([^(|\s]+)\s*(\()?\s*(.*)@', $opt, $match)) {
            $method = $match[1];
            $opt = $match[3];
            $args = [];
            if (!empty($match[2])) {
                while (
                    preg_match('@' . '(?:'
                        . '([-\d.]+)' . '|'
                        . '"((?:[^"]|\\\")*)"' . '|'
                        . "'((?:[^']|\\\')*)'"
                        . ')' . '\s*(,|\))\s*(.*)@', $opt, $match)
                ) {
                    $args[] = $match[1] | $match[2] | $match[3];
                    $opt = $match[5];
                    if (')' == $match[4]) {
                        break;
                    }
                }
            }
            if ('list' == $method) {
                $method = 'acms_corrector_list';
            }
            if ('allow_dangerous_tag' === $method) {
                continue;
            }
            $res = $this->factory->call($method, $txt, $args);

            if ($res !== false) {
                $txt = $res;
            }
        }
        return $txt;
    }
}
