<?php
namespace Tempe;

class Renderer
{
    const P_ROOT = 1;
    const P_STRING = 2;
    const P_BLOCK = 3;
    const P_VALUE = 4;
    const P_ESC = 5;

    public $blockHandlers = [];
    public $valueHandlers = [];
    public $filters = [];

    public $extensions;

    function __construct($extensions=[], $parser=null)
    {
        $this->extensions = $extensions; 
        $this->parser = $parser ?: new Parser;

        foreach ($this->extensions as $e)
            $this->addExtension($e);
    }

    static function createSyntax($options=[])
    {
        $ext = [
            new Ext\Lang(isset($options['lang']) ? $options['lang'] : []), 
            new Ext\String
        ];

        if (isset($options['partial']))
            $ext[] = new Ext\Partial($options['partial']);

        return new static($ext);
    }

    static function createWebSyntax($options=[])
    {
        $r = static::createSyntax($options);
        $filterAs = new Filter\WebEscaper(isset($options['escaper']) ? $options['escaper'] : []);
        $r->addExtension(['filters'=>['as'=>$filterAs]]);
        return $r;
    }

    function addExtension($e)
    {
        $e = (object) $e;
        if (isset($e->blockHandlers)) {
            foreach ($e->blockHandlers as $k=>$h)
                $this->blockHandlers[$k] = $h;
        }

        if (isset($e->valueHandlers)) {
            foreach ($e->valueHandlers as $k=>$h)
                $this->valueHandlers[$k] = $h;
        }

        if (isset($e->filters)) {
            foreach ($e->filters as $k=>$h)
                $this->filters[$k] = $h;
        }
    }

    public function render($template, &$vars=[])
    {
        if (!is_string($template))
            throw new \InvalidArgumentException("Render expects string. Did you mean renderTree()?");

        $tree = $this->parser->parse($template);
        return $this->renderTree($tree, $vars); 
    }

    public function renderTree($tree, &$vars=[])
    {
        $out = '';
        foreach ($tree->c as $node) {
            if ($node->t == self::P_STRING) {
                $out .= $node->v;
            }
            elseif ($node->t == self::P_VALUE || $node->t == self::P_BLOCK) {
                $val = null;

                if (!$node->h)
                    continue;

                if ($node->t == self::P_VALUE) {
                    if (!isset($this->valueHandlers[$node->h]))
                        $this->raise("Unknown value handler {$node->h}", $node);

                    $h = $this->valueHandlers[$node->h];
                    $val = $h($vars, $node->k, $this);
                }
                else {
                    if (!isset($this->blockHandlers[$node->h]))
                        $this->raise("Unknown block handler {$node->h}", $node);

                    $h = $this->blockHandlers[$node->h];
                    $val = $h($vars, $node->k, $node, $this);
                }

                if ($node->f) {
                    foreach ($node->f as $f) {
                        $filter = $f[0];
                        $method = isset($f[1]) ? $f[1] : null;
                        
                        if (!isset($this->filters[$filter]))
                            $this->raise("Unknown filter {$filter}", $node);

                        if ($method)
                            $val = $this->filters[$filter]->$method($val);
                        else
                            $val = $this->filters[$filter]($val);
                    }
                }

                $out .= $val;
            }
            elseif ($node->t == self::P_ESC) {
                $out .= '{';
            }
            else {
                $name = Helper::nodeName($node->t);
                $this->raise("Unexpected node in parse tree: {$name}({$node->t})", $node);
            }
        }

        return $out;
    }

    private function raise($message, \stdClass $node)
    {
        $l = isset($node->l) ? $node->l : 0;
        throw new \RuntimeException("Render failed: $message on line {$l}");
    }
}

