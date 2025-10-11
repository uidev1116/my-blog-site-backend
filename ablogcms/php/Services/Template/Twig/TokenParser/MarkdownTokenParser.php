<?php

namespace Acms\Services\Template\Twig\TokenParser;

use Twig\TokenParser\AbstractTokenParser;
use Twig\Token;
use Twig\Node\Node;
use Twig\Compiler;

class MarkdownTokenParser extends AbstractTokenParser
{
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideMarkdownEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new MarkdownNode($body, $lineno, $this->getTag());
    }

    public function decideMarkdownEnd(Token $token)
    {
        return $token->test('endmarkdown');
    }

    public function getTag()
    {
        return 'markdown';
    }
}

class MarkdownNode extends Node
{
    public function __construct(Node $body, $lineno, $tag = null)
    {
        parent::__construct(['body' => $body], [], $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write("\$markdownContent = ob_get_clean();\n")
            ->write("\$converter = new \cebe\markdown\MarkdownExtra();\n")
            ->write("echo \$converter->parse(\$markdownContent);\n");
    }
}
