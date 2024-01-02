<?php

namespace TwigStorybook\Twig\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use TwigStorybook\Twig\TwigExtension;

final class StoriesNode extends Node
{

    use NodeTrait;

    public function __construct(
        string $title,
        Node $body,
        string $parent_template,
        int $index,
        ?AbstractExpression $variables,
        int $lineno,
        string $tag,
        private readonly string $root,
    ) {
        $nodes = ['body' => $body];
        if ($variables !== null) {
            $nodes['variables'] = $variables;
        }
        parent::__construct($nodes, [], $lineno, $tag);

        $this->setAttribute('index', $index);

        // Set attributes for later.
        $this->setAttribute('title', $title);
        $this->setAttribute('parent_template', $parent_template);
    }

    public function compile(Compiler $compiler): void
    {
        $this->collectStoryMetadata($compiler);
        $compiler->addDebugInfo($this);
        // If the template adds args or parameters at a stories level, then they
        // should be available in the individual story scope.
        $this->compileMergeContext($compiler);
        $this->getNode('body')->compile($compiler);
    }

    /**
     * @param \Twig\Compiler $compiler
     */
    public function collectStoryMetadata(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('if ($context[\'_story\'] === FALSE) {')
            ->indent();
        $stories_id = $this->getAttribute('title');
        // $_stories_meta = ['foo' => 'bar'];
        $compiler->write('$_stories_meta = ');
        $this->hasNode('variables')
        ? $compiler->subcompile($this->getNode('variables'))
        : $compiler->raw('[]');
        $compiler->write(';')->raw(PHP_EOL);
        // Get the extension.
        $compiler->raw('$extension = $this->extensions[')
            ->string(TwigExtension::class)
            ->write('];')
            ->raw(PHP_EOL);

        // Collect all the stories for the given path, as we process them.
        $path = $this->getRelativeTemplatePath($this->root);
        $compiler->raw('$extension->storyCollector->setWrapperData(')
            ->string($path)
            ->raw(', ')
            ->write('$_stories_meta')
            ->raw(');')
            ->raw(PHP_EOL);
        $compiler
            ->outdent()
            ->write('}');
    }
}