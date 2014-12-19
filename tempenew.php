<?php
namespace {

Tempe\create_classes();

$tpl = <<<'EOH'
---
Each of these should print two '{' characters:
{;{;
{;{
---
Should print hello:
{{# if: var foo | is hello | show | trim }}
{{ var foo | as html }}
{{/ if }}
---
Should not print anything:
{{# if: var foo | not hello | show | trim }}
xxx
{{/ if }}
---
Should print yep yep yep:
{{# show | trim }}
{{# set foo }}yep yep yep{{/}}
{{ var foo }}
{{/}}
---
Should print quack quack quack:
{{# show | trim }}
{{# set quack }}quack quack quack{{/}}
{{ var quack | set foo }}
{{ var foo }}
{{/}}
---
Looping:
{{# block: show | trim }}
{{# for: var var | each }}{{ var _idx_ }}. {{ var _key_ }}: {{ var _value_ }}
{{/ for }}
{{/ block }}
---
Should print ass:
{{# push array }}{{ var kid3 }}{{/}}
---
Should print 'ding &amp; dong':
{{# show | as html }}ding & dong{{/}}
---
Should print nothing:
{{# yep: var foo | is hello | hide }}
Should not show
{{/ yep }}
---
Should print nothing:
{{# var foo | hide }}{{# yep: var foo | is hello }}
Should not show
{{/ yep }}{{/}}
EOH;

$vars = ['var'=>['a'=>1, 'b'=>2, 'c'=>3], 'foo'=>'hello', 'array'=>['kid1'=>['kid2'=>['thing'=>'yep']], 'kid3'=>'ass']];

$p = new Tempe\Parser;
$tree = $p->parse($tpl);
Tempe\Helper::dumpNode($tree);

$h = [
    'trim'=>function($in, $params) {
        if ($params->key)
            return trim($in, $params->key);
        else
            return trim($in);
    },

    'var'=>function($in, $params) {
        if (isset($in[$params->key]))
            return $in[$params->key];
        if (isset($params->scope[$params->key]))
            return $params->scope[$params->key];
    },

    'dump'=>function($in, $params) {
        if (isset($params->scope[$params->key])) {
            ob_start();
            var_dump($params->scope[$params->key]);
            return ob_get_clean();
        }
    },

    'is'=>function($in, $params) {
        if ($in != $params->key)
            $params->stop = true;
        else
            return $in;
    },

    'not'=>function($in, $params) {
        if ($in == $params->key)
            $params->stop = true;
        else
            return $in;
    },

    'set'=>function($in, $params) {
        if ($params->node->t == \Tempe\Renderer::P_BLOCK)
            $params->scope[$params->key] = $params->renderer->renderTree($params->node);
        else
            $params->scope[$params->key] = $in;
    },

    'each'=>function($in, $params) {
        if ($params->node->t != \Tempe\Renderer::P_BLOCK)
            throw new \Tempe\RenderException();

        if (is_array($in))
            $iter = $in;
        else
            $iter = $params->scope[$params->key];

        $out = '';
        $idx = 0;
        foreach ($iter as $key=>$item) {
            $kv = [
                '_key_'=>$key, '_value_'=>$item,
                '_first_'=>$idx == 0, '_idx_'=>$idx, '_num_'=>$idx+1
            ];
            $curScope = is_array($item) ? array_merge($params->scope, $item, $kv) : $kv;
            $out .= $params->renderer->renderTree($params->node, $curScope);
            ++$idx;
        }
        return $out;
    },

    'push'=>function($in, $params) {
        if ($params->chainPos != 0 || isset($params->node->h[1]))
            throw new \Tempe\RenderException("Push must be the only handler on line {$params->node->l}");

        $scope = &$params->scope[$params->key];
        return $params->renderer->renderTree($params->node, $scope);
    },

    'show'=>function($in, $params) {
        if ($params->node->t != \Tempe\Renderer::P_BLOCK)
            throw new \Tempe\RenderException();
        return $params->renderer->renderTree($params->node, $params->scope);
    },

    'hide'=>function($in, $params) {
        if ($params->node->t != \Tempe\Renderer::P_BLOCK)
            throw new \Tempe\RenderException();
        return $params->renderer->renderTree($params->node, $params->scope);
    },

    'as'=>function($in, $params) {
        $e = new \Tempe\Filter\WebEscaper;
        return $e->{$params->key}($in);
    },
];

$r = new Tempe\Renderer($h);
echo $r->renderTree($tree, $vars);

}

namespace Tempe { function create_classes() {

require "/home/bl/code/php/tempe/lib/Filter/WebEscaper.php";

class Renderer
{
    const P_ROOT = 1;
    const P_STRING = 2;
    const P_BLOCK = 3;
    const P_VALUE = 4;
    const P_ESC = 5;

    public $handler;
    public $extensions;

    function addHandlers($h)
    {
        foreach ($h as $id=>$f)
            $this->handlers[$id] = $f;
    }

    function __construct($handlers, $parser=null)
    {
        $this->addHandlers($handlers);
        $this->parser = $parser ?: new Parser;
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

                $curHandler = $this->handler;
                $idx = 0;
                foreach ($node->h as $h) {
                    list ($handlerId, $key) = $h;
                    if (!isset($this->handlers[$handlerId]))
                        throw new RenderException("Unknown handler '$handlerId' on line {$node->l}");

                    $param = (object) [
                        'in'=>$val,
                        'scope'=>&$vars,
                        'chainPos'=>$idx,
                        'stop'=>false,
                        'key'=>$key,
                        'node'=>$node,
                        'renderer'=>$this
                    ];
                    $val = $this->handlers[$handlerId]($val, $param);
                    ++$idx;

                    if ($param->stop)
                        break;
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


class Parser
{
    const M_STRING = 1;
    const M_TAG = 2;

    public static $identifierPattern = '[a-zA-Z\d_]([a-zA-Z_\/\.\-\d]*[a-zA-Z\d])*';

    function tokenise($in)
    {
        $pattern = '~( \{; | \{\{[#/]? | \}\} | \r\n | \n | \r )~x';

        $flags = PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY;
        $tokens = preg_split($pattern, $in, null, $flags);
        return $tokens;
    }

    function parse($tokens)
    {
        if (!is_array($tokens))
            $tokens = $this->tokenise($tokens);

        $line = 1;
        $tree = (object)['t'=>Renderer::P_ROOT, 'i'=>null, 'c'=>[], 'l'=>$line];
        $node = $tree;
        $stack = [$node];
        $stackIdx = 0;

        $i = 0;
        $tokenLen = count($tokens);

        $currentMode = self::M_STRING;
        $tagType = null;

        $bufferLine = $line;
        $buffer = '';

        while ($i < $tokenLen) {
            $current = $tokens[$i++];
            $isNewline = ($current[0] == "\r" || $current[0] == "\n");

            if ($isNewline)
                ++$line;

            switch ($currentMode) {
            case self::M_STRING:
                if ($current == '{;' || $current == '{{' || $current == '{{/' || $current == '{{#') {
                    if ($buffer) {
                        $node->c[] = (object)['t'=>Renderer::P_STRING, 'v'=>$buffer, 'l'=>$bufferLine];
                        $bufferLine = $line;
                    }
                    if ($current == '{;') {
                        $buffer = '';
                        $node->c[] = (object)['t'=>Renderer::P_ESC, 'v'=>$current, 'l'=>$line];
                    }
                    else {
                        $buffer = "";
                        $currentMode = self::M_TAG;
                        $tagType = isset($current[2]) ? $current[2] : null;
                    }
                }
                else {
                    $buffer .= $current;
                }
            break;

            case self::M_TAG:
                switch ($current) {
                
                // MUST keep this case in sync with the tokens from the tokeniser
                case '{;': case '{{': case '{{#': case '{{/':
                    throw new ParseException("Unexpected $current at line $line");
                break;

                // OK we have the whole tag, now subparse it.
                case '}}':
                    $tagString = "{{{$tagType}{$buffer}}}";
                    $newNode = null;

                    // create a new node if it's not a block close
                    if ($tagType != '/') {
                        $newNode = (object)[
                            't' => ($tagType == '/' || $tagType == '#') 
                                ? Renderer::P_BLOCK 
                                : Renderer::P_VALUE,
                            'l' => $bufferLine,
                            'i' => null,
                            'h' => [],
                        ];

                        $pattern = '/(?: \s+ | ([:\|]) )/x';
                        $flags = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE;
                        $tagTokens = preg_split($pattern, $buffer, null, $flags);
                        $tagTokens[] = null;

                        $ttcur = [];
                        foreach ($tagTokens as $t) {
                            if ($t == ':') {
                                if (isset($ttcur[1])) throw new ParseException("Invalid ID");
                                $newNode->i = $ttcur[0];
                                $ttcur = [];
                            }
                            elseif ($t == '|' || $t === null) {
                                if (isset($ttcur[2])) throw new ParseException("Invalid ID");
                                if (!isset($ttcur[1])) $ttcur[1] = null;
                                $newNode->h[] = $ttcur;
                                $ttcur = [];
                            }
                            else {
                                $ttcur[] = $t;
                            }
                        }
                    } // end create

                    { // stack handling
                        if ($tagType == '#') {
                            $newNode->c = [];
                            $newNode->vo = $tagString;

                            $node = $node->c[] = $newNode;
                            $stack[++$stackIdx] = $node;
                        }
                        elseif ($tagType == '/') {
                            // validate only: it's a block close tag
                            if ($node->t != Renderer::P_BLOCK)
                                throw new ParseException("Block close found, but no block open", $line);

                            $id = trim($buffer);
                            if (($node->i || $id) && $node->i != $id)
                                throw new ParseException("Block close mismatch. Expected '{$node->i}', found '$id'");
                            $node->vc = $tagString;
                            $node = $stack[--$stackIdx];

                        }
                        else {
                            $newNode->v = $tagString;
                            $node->c[] = $newNode;
                        }
                    }

                    { // cleanup
                        $buffer = '';
                        $bufferLine = $line;
                        $currentMode = self::M_STRING;
                    }
                break;

                default:
                    $buffer .= $current;
                break;
                }

            break;
            }
        }

        if ($currentMode == self::M_TAG)
            throw new ParseException("Tag close mismatch, open was on line $bufferLine");
        if ($node != $tree)
            throw new ParseException("Unclosed block '".($node->i ?: "(unnamed)")."'", $node->l);

        if ($buffer) {
            $node->c[] = (object)[
                't'=>Renderer::P_STRING, 'v'=>$buffer, 'l'=>$bufferLine,
            ];
        }

        return $tree;
    }

    function unparse($tree)
    {
        $stack = [(object)['n'=>$tree, 'i'=>0]];
        $stackIdx = 0;

        $out = '';
        $stackNode = $stack[$stackIdx];
        while (true) {
            $current = isset($stackNode->n->c[$stackNode->i]) ? $stackNode->n->c[$stackNode->i] : null;
            if (!$current) {
                if ($stackIdx <= 0)
                    break;
                
                if ($stackNode->n->t == Renderer::P_BLOCK)
                    $out .= $stackNode->n->vc;
                $stackNode = $stack[--$stackIdx];
            }
            else {
                ++$stackNode->i;
                switch ($current->t) {
                    case Renderer::P_BLOCK:
                        $stackNode = $stack[++$stackIdx] = (object)['n'=>$current, 'i'=>0];
                        $out .= $current->vo;
                    break;

                    case Renderer::P_STRING:
                    case Renderer::P_VALUE:
                    case Renderer::P_ESC:
                        $out .= $current->v;
                    break;

                    default:
                        throw new \Exception("Cannot unparse ".Helper::tokenName($current->t)."({$current->t})");
                }
            }
        }

        return $out;
    }
}
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

            $nodeName = isset($node->i) ? $node->i : static::nodeName($node->t);
            $nodeNameLen = strlen($nodeName);
            if ($nodeNameLen > $max['nameLen']) $max['nameLen'] = $nodeNameLen;

            $flat[] = [$node, $nodeName, $depth];
            
            if (isset($node->c) && $options['recurse']) {
                foreach ($node->c as $node)
                    $flatten($node, $depth+1);
            }
        };
        $flatten($node);

        // I'm not sure I've ever managed to do this columnar stuff with 
        // widths in a way that wasn't hideous.
        $max['lineLen'] = strlen($flat[$count-1][0]->l);
        $max['depthLen'] = strlen($max['depth']);
        $spacingLen = 2;
        
        $maxTreeWidth = $max['depthLen']
            + ($options['indent'] * $max['depth'] * strlen($options['indentStr'])) 
            + $max['lineLen']
            + $max['nameLen']
            + $spacingLen;
        
        if ($options['termColour']) {
            $treeFmt = "\e[90m%{$max['depthLen']}s\e[0m  \e[94m%{$max['lineLen']}s\e[0m %s\e[{nc}m%s\e[0m";
            $handlerFmt = "\e[94m%s\e[36m%s\e[0m";
            $tableFmt = "%s%s  \e[90m|\e[0m  %s\n";
        }
        else {
            $treeFmt = "%{$max['depthLen']}s  %{$max['lineLen']}s %s%s";
            $handlerFmt = "%s%s";
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
            $tf = str_replace("{nc}", isset($node->i) ? "1;92" : "0", $treeFmt);
            $nodeStr = sprintf($tf, $depth, $node->l, $ws, $name);

            $value = "";
            if ($node->t == Renderer::P_BLOCK || $node->t == Renderer::P_VALUE) {
                $value = [];
                foreach ($node->h as $hc) {
                    $value[] = sprintf($handlerFmt, $hc[0], isset($hc[1]) ? " {$hc[1]}" : null);
                }
                $value = implode(" -> ", $value);
            }
            elseif ($node->t == Renderer::P_STRING) {
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
            $t = $t->t;

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

class RenderException extends \RuntimeException
{
    function __construct($message, $line=null)
    {
        if ($line)
            $message .= " at line {$line}";

        parent::__construct(trim($message));
    }
}

class ParseException extends \RuntimeException
{
    function __construct($message, $line=null)
    {
        if ($line)
            $message .= " at line {$line}";

        parent::__construct(trim($message));
    }
}

}}
