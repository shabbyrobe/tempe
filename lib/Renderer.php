<?php
namespace Tempe;

class Renderer
{
    const P_ROOT = 1;
    const P_STRING = 2;
    const P_BLOCK = 3;
    const P_VALUE = 4;
    const P_ESC = 5;

    public $handlers;

    function __construct(Lang $lang=null, $parser=null, $check=false)
    {
        $this->lang = $lang ?: Lang\Factory::createBasic();
        $this->check = $check;
        $this->parser = $parser ?: new Parser($lang);
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
        $param = new HandlerContext;
        $param->scope = &$vars;
        $param->renderer = $this;

        if (!isset($tree->nodes))
            return;

        foreach ($tree->nodes as $node) {
            $param->node = $node;

            if ($node->type == self::P_STRING) {
                $out .= $node->v;
            }
            elseif ($node->type == self::P_VALUE || $node->type == self::P_BLOCK) {
                $val = null;

                if (!$node->chain)
                    continue;

                $param->stop = false;

                foreach ($node->chain as $param->chainPos=>$h) {
                    $param->argc = $h['argc'];
                    $param->args = $h['args'];
                    if ($this->check)
                        $this->lang->check($h, $node, $param->chainPos);

                    $val = $this->lang->handle($h, $val, $param);
                    if ($param->stop)
                        break;
                }
                $out .= @(string) $val;
            }
            elseif ($node->type == self::P_ESC) {
                $out .= '{';
            }
            else {
                $name = Helper::nodeName($node->type);
                $this->raise("Unexpected node in parse tree: {$name}({$node->type})", $node);
            }
        }

        return $out;
    }

    private function raise($message, \stdClass $node)
    {
        $l = isset($node->line) ? $node->line : 0;
        throw new \RuntimeException("Render failed: $message on line {$l}");
    }
}
