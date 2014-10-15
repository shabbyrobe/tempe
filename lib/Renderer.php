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
    public $pipeHandlers = [];

    public $extensions;

    function __construct($extensions=[], $parser=null)
    {
        $this->extensions = $extensions; 
        $this->parser = $parser ?: new Parser;

        foreach ($this->extensions as $e)
            $this->addExtension($e);
    }

    static function createBasic($options=[])
    {
        $ext = [
            new Ext\Lang(isset($options['lang']) ? $options['lang'] : []), 
            new Ext\String
        ];
        return new static($ext);
    }

    static function createBasicWeb($options=[])
    {
        $r = static::createBasic($options);
        $filterAs = new Filter\WebEscaper(isset($options['escaper']) ? $options['escaper'] : []);
        $r->addExtension(['pipeHandlers'=>['as'=>$filterAs]]);
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

        if (isset($e->pipeHandlers)) {
            foreach ($e->pipeHandlers as $k=>$h)
                $this->pipeHandlers[$k] = $h;
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

                if (!$node->hc)
                    continue;

                $ph = $node->hc[0];

                if ($node->t == self::P_VALUE) {
                    if (!isset($this->valueHandlers[$ph->h]))
                        $this->raise("Unknown value handler {$ph->h}", $node);

                    $h = $this->valueHandlers[$ph->h];
                    $val = $h($vars, $ph->k, $this, $node);
                }
                else {
                    if (!isset($this->blockHandlers[$ph->h]))
                        $this->raise("Unknown block handler {$ph->h}", $node);

                    $h = $this->blockHandlers[$ph->h];
                    $val = $h($vars, $ph->k, $this, $node);
                }

                $i = 1;
                while (isset($node->hc[$i])) {
                    $ch = $node->hc[$i];
                    if (!isset($this->pipeHandlers[$ch->h]))
                        $this->raise("Unknown pipe handler {$ch->h}", $node);

                    $h = $this->pipeHandlers[$ch->h];
                    if ($ch->k)
                        $val = $h->{$ch->k}($val, $vars, $this, $node);
                    else
                        $val = $h($val, $vars, $this, $node);
                    $i++;
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

