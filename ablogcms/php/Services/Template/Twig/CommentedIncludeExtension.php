<?php

namespace Acms\Services\Template\Twig;

use Acms\Services\Template\Twig\IncludeCommentHelper;
use Acms\Services\Template\Twig\NodeVisitor\CommentedIncludeNodeVisitor;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TemplateWrapper;
use Twig\TwigFunction;

/**
 * {% include %} タグおよび {{ include() }} 関数の前後にデバッグ用 HTML コメントを出力する Extension。
 * Environment の 'debug' => true のときのみ有効。
 */
class CommentedIncludeExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('include', [$this, 'includeWithComment'], [
                'needs_environment' => true,
                'needs_context' => true,
                'is_safe' => ['all'],
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function includeWithComment(
        Environment $env,
        array $context,
        mixed $template,
        array $variables = [],
        bool $withContext = true,
        bool $ignoreMissing = false,
        bool $sandboxed = false
    ): string {
        $output = CoreExtension::include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);

        $name = is_string($template) ? $template
            : ($template instanceof TemplateWrapper ? $template->getTemplateName() : '(unknown)');

        if (!$env->isDebug() || !IncludeCommentHelper::isHtmlTemplate($name)) {
            return $output;
        }

        $source = IncludeCommentHelper::resolveTemplatePath($name, $env);

        return '<!-- Start of include : source=' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8') . ' -->' . PHP_EOL
            . $output
            . PHP_EOL . '<!-- End of include : source=' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8') . ' -->' . PHP_EOL;
    }

    /**
     * @return list<NodeVisitorInterface>
     */
    public function getNodeVisitors(): array
    {
        return [new CommentedIncludeNodeVisitor()];
    }
}
