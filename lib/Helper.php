<?php
namespace Tempe;

use Tempe\Renderer;

class Helper
{
    static function dumpNode($node, $options=[])
    {
        $options = array_merge(
            [
                'termColour'=>php_sapi_name() == 'cli',
                'recurse'=>true,
                'indent'=>2,
                'indentStr'=>' '
            ],
            $options
        );

        $max = ['depth'=>0, 'nameLen'=>0];
        $count = 0;

        $flatten = function($node, $depth=0) use (&$flat, &$count, &$max, $options, &$flatten) {
            $count++;
            if ($flat == null) $flat = [];
            if ($depth > $max['depth']) $max['depth'] = $depth;

            $nodeName = isset($node->id) ? $node->id : static::nodeName($node->type);
            $nodeNameLen = strlen($nodeName);
            if ($nodeNameLen > $max['nameLen']) $max['nameLen'] = $nodeNameLen;

            $flat[] = [$node, $nodeName, $depth];
            
            if (isset($node->nodes) && $options['recurse']) {
                foreach ($node->nodes as $node)
                    $flatten($node, $depth+1);
            }
        };
        $flatten($node);

        // I'm not sure I've ever managed to do this columnar stuff with 
        // widths in a way that wasn't hideous.
        $max['lineLen'] = strlen($flat[$count-1][0]->line);
        $max['depthLen'] = strlen($max['depth']);
        $spacingLen = 2;
        
        $maxTreeWidth = $max['depthLen']
            + ($options['indent'] * $max['depth'] * strlen($options['indentStr'])) 
            + $max['lineLen']
            + $max['nameLen']
            + $spacingLen;
        
        if ($options['termColour']) {
            $treeFmt = "\e[90m%{$max['depthLen']}s\e[0m  \e[94m%{$max['lineLen']}s\e[0m %s\e[{nc}m%s\e[0m";
            $handlerFmt = "\e[94m%s (\e[36m%s\e[94m)\e[0m";
            $tableFmt = "%s%s  \e[90m|\e[0m  %s\n";
        }
        else {
            $treeFmt = "%{$max['depthLen']}s  %{$max['lineLen']}s %s%s";
            $handlerFmt = "%s %s";
            $tableFmt = "%s%s  |  %s\n";
        }

        foreach ($flat as $item) {
            list ($node, $name, $depth) = $item;
            $ws = str_repeat($options['indentStr'], $depth * $options['indent']);
            
            $treePadLen = $maxTreeWidth 
                - $max['depthLen']
                - $max['lineLen']
                - strlen($name)
                - strlen($ws)
                - $spacingLen;

            $treePad = str_repeat(' ', $treePadLen);
            $tf = str_replace("{nc}", isset($node->id) ? "1;92" : "0", $treeFmt);
            $nodeStr = sprintf($tf, $depth, $node->line, $ws, $name);

            $value = "";
            if ($node->type == Renderer::P_BLOCK || $node->type == Renderer::P_VALUE) {
                $value = [];
                foreach ($node->chain as $hc) {
                    $args = $hc['args'] ? implode(' ', $hc['args']) : '';
                    $value[] = sprintf($handlerFmt, $hc['handler'], $args);
                }
                $value = implode(" -> ", $value);
            }
            elseif ($node->type == Renderer::P_STRING) {
                $v = $node->v;
                if (strlen($v) > 20)
                    $v = substr($v, 0, 20).'...';
                $value = json_encode($v);
            }
            echo sprintf($tableFmt, $nodeStr, $treePad, $value);
        }
    }

    static function nodeName($t)
    {
        if ($t instanceof \stdClass)
            $t = $t->type;

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
