<?php
namespace Tempe;

use Tempe\Renderer;

class Helper
{
    static function dumpTree($node, $recurse=true, $depth=0)
    {
        $node = (object)$node;

        echo str_repeat("  ", $depth * 2);
        echo static::nodeName($node->t).":   ";
        foreach (get_object_vars($node) as $k=>$v) {
            if ($k == 'c' || $k == 't') {
                continue;
            }
            if (is_string($v) && strlen($v) > 40) {
                $v = substr($v, 0, 40).'...';
            }
            echo "$k=".json_encode($v)."   ";
        }
        echo "\n";

        if (isset($node->c) && $recurse) {
            foreach ($node->c as $node) {
                self::dumpTree($node, $recurse, $depth+1);
            }
        }
    }

    static function nodeName($t)
    {
        if ($t instanceof \stdClass) {
            $t = $t->t;
        }

        switch ($t) {
        case Renderer::P_ROOT:
            return 'P_ROOT';
        case Renderer::P_STRING:
            return 'P_STRING';
        case Renderer::P_BLOCK:
            return 'P_BLOCK';
        case Renderer::P_VALUE:
            return 'P_VALUE';
        case Renderer::P_ESC:
            return 'P_ESC';
        default:
            return 'UNKNOWN';
        }
    }
}
