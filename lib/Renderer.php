<?php
namespace Tempe;

class Renderer
{
    const P_ROOT = 1;
    const P_STRING = 2;
    const P_BLOCK = 3;
    const P_VALUE = 4;
    const P_ESC_VAL = 5;
    const P_ESC_BCLOSE = 6;
    const P_ESC_BOPEN = 7;

    public $handlers;

    function __construct(Lang $lang=null, $parser=null, $check=false)
    {
        $this->lang = $lang ?: Lang\Factory::createDefault();
        $this->check = $check;
        $this->parser = $parser ?: new Parser($lang);
    }

    public function render($template, &$scope=[])
    {
        if (!is_string($template)) {
            throw new \InvalidArgumentException("Render expects string. Did you mean renderTree()?");
        }
        $tree = $this->parser->parse($template);
        return $this->renderTree($tree, $scope); 
    }

    public function renderTree($tree, &$scope=[])
    {
        $out = '';
        $context = new HandlerContext;
        $context->scope = &$scope;
        $context->renderer = $this;

        if (!isset($tree->nodes)) {
            return;
        }

        // only the root node has a version
        if (isset($tree->version) && $tree->version != Parser::VERSION) {
            throw new \InvalidArgumentException(
                'Tree version '.(isset($tree->version) ? $tree->version : '(null)').
                ' does not match expected version '.Parser::VERSION);
        }

        foreach ($tree->nodes as $node) {
            $context->node = $node;

            if ($node->type == self::P_STRING) {
                $out .= $node->v;
            }
            elseif ($node->type == self::P_VALUE || $node->type == self::P_BLOCK) {
                $val = null;
                $context->break = false;
                
                if (!$node->chain) {
                    $val = $this->lang->handleEmpty($context);
                }
                else {
                    foreach ($node->chain as $context->chainPos=>$h) {
                        if ($this->check) {
                            $this->lang->check($h, $node, $context->chainPos);
                        }
                        $val = $this->lang->handle($h, $val, $context);
                        if ($context->break) {
                            break;
                        }
                    }
                }

                // 'true' is cast to 1 so let's skip it
                if ($val !== true) {
                    $out .= @(string) $val;
                }
            }
            elseif ($node->type == self::P_ESC_VAL) {
                $out .= '{=';
            }
            elseif ($node->type == self::P_ESC_BOPEN) {
                $out .= '{#';
            }
            elseif ($node->type == self::P_ESC_BCLOSE) {
                $out .= '{/';
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
