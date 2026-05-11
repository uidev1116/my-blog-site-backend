<?php

namespace Acms\Services\Template;

/**
 * テンプレートパスに対する Twig 統合の利用可否を判定する。
 *
 * a-blog cms のテンプレート探索（function findTemplate / main.php のレンダリング
 * 分岐）から本クラスを唯一の窓口として呼び、`tplConfig('tpl_twig')` の値と
 * 除外パスリスト（{@see self::EXCLUDED_PATHS}）の両方を AND した最終結果を
 * 返す。Twig 利用判定のロジックを 1 箇所に集約することで、呼び出し側の
 * 重複と判定漏れを防ぐ。
 *
 * v3.2.0 ~ v3.2.19 で、不要な twig ファイルが system テーマに残っており、
 * かつテーマ側で edit.twig を直接 include する等の外部依存があるため、
 * 特定のベース名については意図的に Twig 化を行わないようにする。
 *
 * 将来的に外部依存が完全に解消され、対応する .twig stub を物理削除できるようになった時点で、除外パスを外す。
 *
 * 本クラスは a-blog cms 側のテンプレート探索のみで参照され、Twig 内部
 * include 経路（CustomLoader）には作用しない。両者を分離することで、
 * テーマ作者が独自に .twig 化した別パスへの影響を避けつつ、JS のダイレクト
 * 編集 fetch 等で .html を確実に解決させる。
 *
 */
final class TwigPolicy
{
    /**
     * Twig 化を行わないベース名（拡張子なし・先頭スラッシュ抜き）。
     *
     * @var list<string>
     */
    public const EXCLUDED_PATHS = [
        'admin/entry/edit',
    ];

    /**
     * 与えられたパスについて、最終的に Twig 統合を使うべきかを判定する。
     *
     * `tplConfig('tpl_twig') === 'enabled'` と {@see self::excludes()} の
     * 否定の AND を取った結果を返す。findTemplate / レンダリング分岐の
     * 両方から本メソッドを呼ぶ。
     */
    public function shouldUseFor(string $tpl): bool
    {
        if (tplConfig('tpl_twig') !== 'enabled') {
            return false;
        }
        if ($this->excludes($tpl)) {
            return false;
        }
        return true;
    }

    /**
     * 与えられたテンプレートパスが除外リストに該当するかを判定する。
     *
     * パスは正規化（連続スラッシュ・バックスラッシュ・先頭スラッシュ）された
     * うえでベース名（拡張子を除いた相対パス）として比較される。
     */
    private function excludes(string $tpl): bool
    {
        $normalized = preg_replace('#/{2,}#', '/', str_replace('\\', '/', trim($tpl)));
        if (!is_string($normalized) || $normalized === '') {
            return false;
        }

        $info = pathinfo($normalized);
        $rawDirname = $info['dirname'];
        $dirname = ($rawDirname !== '.' && $rawDirname !== '')
            ? $rawDirname . '/'
            : '';
        $filename = $info['filename'];

        if ($filename === '') {
            return false;
        }

        $base = ltrim($dirname . $filename, '/');

        return in_array($base, self::EXCLUDED_PATHS, true);
    }
}
