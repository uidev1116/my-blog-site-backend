<?php

namespace Acms\Services\Template\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\IncludeNode;

/**
 * IncludeNode を拡張し、デバッグ用 HTML コメントを前後に出力する。
 * ランタイムで IncludeCommentHelper を呼び、テーマパスを含めたソースパスを表示する。
 */
#[YieldReady]
class CommentedIncludeNode extends IncludeNode
{
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        $compiler->write("\$__include_tpl__ = (string) ");
        $compiler->subcompile($this->getNode('expr'));
        $compiler->raw(";\n");

        $compiler->write("\$__include_is_html__ = \\Acms\\Services\\Template\\Twig\\IncludeCommentHelper::isHtmlTemplate(\$__include_tpl__);\n");
        $compiler->write("if (\$__include_is_html__) {\n");
        $compiler->indent();
        $compiler->write("\$__include_source__ = \\Acms\\Services\\Template\\Twig\\IncludeCommentHelper::resolveTemplatePath(\$__include_tpl__, \$this->env);\n");
        $compiler->write("yield '<!-- Start of include : source=' . htmlspecialchars(\$__include_source__, ENT_QUOTES, 'UTF-8') . ' -->' . PHP_EOL;\n");
        $compiler->outdent();
        $compiler->write("}\n");

        parent::compile($compiler);

        $compiler->write("if (\$__include_is_html__) {\n");
        $compiler->indent();
        $compiler->write("yield '<!-- End of include : source=' . htmlspecialchars(\$__include_source__, ENT_QUOTES, 'UTF-8') . ' -->' . PHP_EOL;\n");
        $compiler->outdent();
        $compiler->write("}\n");
    }
}
