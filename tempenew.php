<?php
namespace {

Tempe\create_classes();
Tempe\throw_on_error();

$rules = [
    'trim' => ['argc'=>0],
    'var'  => ['argc'=>1],
    'dump' => ['argc'=>0],
    'is'   => ['argc'=>1],
    'not'  => ['argc'=>1],
    'each' => ['argMin'=>0, 'argMax'=>1, 'allowValue'=>false],
    'set'  => ['argc'=>1],
    'as'   => ['argc'=>1],
    'push' => ['argc'=>1, 'chainable'=>false],
    'show' => ['argc'=>0, 'allowValue'=>false],
    'hide' => ['argc'=>0, 'allowValue'=>false],
];

$h = [
    'trim'=>function($in, $context) {
        return trim($in);
    },

    'var'=>function($in, $context) {
        $scopeInput = false;

        $key = isset($context->args[0]) ? $context->args[0] : null;
        if ($key && $in !== '' && $in !== null) {
            $scopeInput = true;
            $scope = $in;
            if (!is_array($scope) && !$scope instanceof \ArrayAccess)
                throw new \Exception\Render("Input scope was not an array or ArrayAccess", $context->node->line);
        }
        else {
            $scope = $context->scope;
        }

        if ($in && !$key)
            $key = $in;

        if (!array_key_exists($key, $scope))
            throw new Exception\Render("Could not find key '$key' in ".($scopeInput ? 'input' : 'context')." scope", $context->node->line);

        return $scope[$key];
    },

    'dump'=>function($in, $context) {
        ob_start();
        var_dump($in);
        return ob_get_clean();
    },

    'is'=>function($in, $context) {
        if ($in != $context->args[0])
            $context->stop = true;
        else
            return $in;
    },

    'not'=>function($in, $context) {
        if ($in == $context->args[0])
            $context->stop = true;
        else
            return $in;
    },

    'set'=>function($in, $context) {
        $key = $context->args[0];
        if ($context->node->type == \Tempe\Renderer::P_BLOCK)
            $context->scope[$key] = $context->renderer->renderTree($context->node);
        else
            $context->scope[$key] = $in;
    },

    'each'=>function($in, $context) {
        $key = $context->argc == 1 ? $context->args[0] : null;
        if ($in)
            $iter = $in;
        elseif ($key)
            $iter = $context->scope[$key];
        else
            return;

        $out = '';
        $idx = 0;

        // add or remove ampersand to switch 'each' hoisting
        $scope = &$context->scope;

        $loop = ['_key_'=>null, '_value_'=>null, '_idx_'=>0, '_num_'=>1, '_first_'=>true];

        foreach ($iter as $loop['_key_']=>$loop['_value_']) {
            $scope['_key_']   = $loop['_key_'];
            $scope['_value_'] = $loop['_value_'];
            $scope['_idx_']   = $loop['_idx_'] = $idx;
            $scope['_num_']   = $loop['_num_'] = $idx + 1;
            $scope['_first_'] = $loop['_first_'] = $idx == 0;
            $scope['_loop_']  = $loop;

            if (!is_scalar($loop['_value_'])) {
                foreach ((array) $loop['_value_'] as $k=>$v)
                    $scope[$k] = $v;
            }

            $out .= $context->renderer->renderTree($context->node, $scope);
            ++$idx;
        }
        return $out;
    },

    'push'=>function($in, $context) {
        $scope = &$context->scope[$context->args[0]];
        return $context->renderer->renderTree($context->node, $scope);
    },

    'show'=>function($in, $context) {
        return $context->renderer->renderTree($context->node, $context->scope);
    },

    'hide'=>function($in, $context) {
        return $context->renderer->renderTree($context->node, $context->scope);
    },

    'as'=>function($in, $context) {
        static $e;
        if (!$e)
            $e = new \Tempe\Filter\WebEscaper;
        return $e->{$context->args[0]}($in);
    },

    'test'=>function($in, $context) {
        $out = $in;
        foreach ($context->args as $idx=>$arg) {
            if ($idx) $out .= ' ';
            $out .= $arg;
        }
        return $out;
    },
];

$tpl = <<<'EOT'
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
{{# ifff: var foo | not hello | show | trim }}
xxx
{{/ ifff }}
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
{{# for: each var }}{{ var _num_ }} ({{ var _idx_ }}) {{ var _key_ }}: {{ var _value_ }}
{{/ for }}
{{/ block }}
---
Nested looping - access parent loop item:
{{# block: show | trim }}
{{# for: each nest | trim }}
{{# each b }}{{ var _key_ }} {{ var _value_ }}, {{/ }} 
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
EOT;

$vars = [
    'var'=>[
        'a'=>'z', 'b'=>'x', 'c'=>'y',
    ], 
    'nest'=>[
        ['b'=>[1, 2, 3]],
        ['b'=>[3, 4, 5]],
        ['b'=>[5, 6, 7]],
    ],
    'foo'=>'hello', 
    'array'=>['kid1'=>['kid2'=>['thing'=>'yep']], 'kid3'=>'ass'],
    'ass'=>'grr',
    'pants'=>[
        'a'=>'foo',
        'b'=>'bar',
        'c'=>'baz',
        'd'=>'qux',
    ],
];

$lang = new Tempe\BasicLang($h, $rules);

$p = new Tempe\Parser($lang);
$s = microtime(true);
for ($i=0; $i<1; $i++)
    $tree = $p->parse($tpl);
$pt = microtime(true) - $s;

Tempe\Helper::dumpNode($tree);
$r = new Tempe\Renderer($lang);

$out = null;
$s = microtime(true);
for ($i=0; $i<10; $i++) {
    $tout = $r->renderTree($tree, $vars);
    if (!$out) $out = $tout;
}
$rt = microtime(true) - $s;

echo $out."\n";
echo "parse time:  $pt\n";
echo "render time: $rt\n";

if (isset($GLOBALS['time']))
    echo $GLOBALS['time']."\n";
}

namespace Tempe { function create_classes() {

require "/home/bl/code/php/tempe/lib/Filter/WebEscaper.php";

interface Lang
{
    function check(array $handler, $node, $chainPos);
    function handle(array $handler, $in, HandlerContext $context);
}

class HandlerContext
{
    public $scope;
    public $chainPos = 0;
    public $stop = false;
    public $args;
    public $argc;
    public $node;
    public $renderer;
}

class BasicLang implements Lang
{
    function __construct(array $handlers=[], array $rules=[])
    {
        $this->handlers = $handlers;
        $this->rules = $rules;
    }

    function check(array $handler, $node, $chainPos)
    {
        $hid = $handler['handler'];

        if (!isset($this->handlers[$hid]))
            throw new \Tempe\CheckException();

        if (isset($this->rules[$hid])) {
            $rule = $this->rules[$hid];
            if (isset($rule['argc'])) {
                if ($handler['argc'] != $rule['argc'])
                throw new \Tempe\CheckException("Handler '$hid' expected {$rule['argc']} arg(s), found {$handler['argc']} on line {$node->line}");
            }
            else {
                if (isset($rule['argMin']) && $handler['argc'] < $rule['argMin'])
                    throw new \Tempe\CheckException("Handler '$hid' min args {$rule['argMin']}, found {$handler['argc']} on line {$node->line}");

                if (isset($rule['argMax']) && $handler['argc'] > $rule['argMax'])
                    throw new \Tempe\CheckException("Handler '$hid' max args {$rule['argMax']}, found {$handler['argc']} on line {$node->line}");
            }

            if (isset($rule['allowValue']) && !$rule['allowValue'] && $node->type == \Tempe\Renderer::P_VALUE)
                throw new \Tempe\CheckException("Handler '$hid' can not be used with a value tag on line {$node->line}");
            
            if (isset($rule['allowBlock']) && !$rule['allowBlock'] && $node->type == \Tempe\Renderer::P_BLOCK)
                throw new \Tempe\CheckException("Handler '$hid' can not be used with a block tag on line {$node->line}");

            if (isset($rule['chainable']) && !$rule['chainable'] && ($chainPos != 0 || isset($node->chain[1])))
                throw new \Tempe\CheckException("Handler '$hid' is not chainable on line {$node->line}");

            if (isset($rule['check']) && !$rule['check']($node, $handler, $chainPos))
                throw new \Tempe\CheckException("Handler '$hid' check failed on line {$node->line}");
        }
    }

    function handle(array $handler, $val, HandlerContext $context)
    {
        $h = $this->handlers[$handler['handler']];
        return $h($val, $context);
    }
}

class CheckException extends \RuntimeException {}

class Renderer
{
    const P_ROOT = 1;
    const P_STRING = 2;
    const P_BLOCK = 3;
    const P_VALUE = 4;
    const P_ESC = 5;

    public $handlers;

    function __construct(Lang $lang, $parser=null, $check=false)
    {
        $this->lang = $lang;
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


class Parser
{
    const M_STRING = 1;
    const M_TAG = 2;

    private $patternTag;
    private $lang;

    function __construct(Lang $lang=null) 
    {
        $this->lang = $lang;

        $identifier = "[a-zA-Z_\/\.\-\d]+";
        $this->patternTag = "
            /^ 
                (?: (?P<oid> ".$identifier." ): )? 
                (?P<ochain> 
                    (?: \s* ".$identifier." \s* )+ 
                    (?: \| (?: \s* ".$identifier." \s* )+ )*
                )
            $/x
        ";
    }

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
        $tree = (object)['type'=>Renderer::P_ROOT, 'id'=>null, 'nodes'=>[], 'line'=>$line];
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
                        $node->nodes[] = (object)['type'=>Renderer::P_STRING, 'v'=>$buffer, 'line'=>$bufferLine];
                        $bufferLine = $line;
                    }
                    if ($current == '{;') {
                        $buffer = '';
                        $node->nodes[] = (object)['type'=>Renderer::P_ESC, 'v'=>$current, 'line'=>$line];
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
                        $newNode = $this->createNode($tagString, $tagType, $buffer, $bufferLine);
                    }

                    { // stack handling
                        if ($tagType == '#') {
                            $newNode->nodes = [];
                            $newNode->vo = $tagString;

                            $node = $node->nodes[] = $newNode;
                            $stack[++$stackIdx] = $node;
                        }
                        elseif ($tagType == '/') {
                            // validate only: it's a block close tag
                            if ($node->type != Renderer::P_BLOCK)
                                throw new ParseException("Block close found, but no block open", $line);

                            $id = trim($buffer);
                            if (($node->id || $id) && $node->id != $id)
                                throw new ParseException("Block close mismatch. Expected '{$node->id}', found '$id'", $line);
                            $node->vc = $tagString;
                            $node = $stack[--$stackIdx];

                        }
                        else {
                            $newNode->v = $tagString;
                            $node->nodes[] = $newNode;

                            if ($this->lang) {
                                foreach ($newNode->chain as $pos=>$handler)
                                    $this->lang->check($handler, $newNode, $pos);
                            }
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
            throw new ParseException("Unclosed block '".($node->id ?: "(unnamed)")."'", $node->line);

        if ($buffer) {
            $node->nodes[] = (object)[
                'type'=>Renderer::P_STRING, 'v'=>$buffer, 'line'=>$bufferLine,
            ];
        }

        return $tree;
    }

    private function createNode($tagString, $tagType, $buffer, $bufferLine)
    {
        $newNode = (object)[
            'type' => ($tagType == '/' || $tagType == '#') 
                ? Renderer::P_BLOCK 
                : Renderer::P_VALUE,
            'line' => $bufferLine,
            'id' => null,
            'chain' => [],
        ];

        $ok = preg_match($this->patternTag, trim($buffer), $m);
        
        // Unfortunately, this sacrifices the quality of the error for
        // parsing speed. Maybe there's a middle ground.
        if (!$ok)
            throw new ParseException('Invalid tag: '.$tagString, $bufferLine);

        if ($m['oid'])
            $newNode->id = $m['oid'];

        $tok = preg_split('~(?: \s* (\|) \s* | \s+ )~x', $m['ochain'], null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $tok[] = null;

        $h = null;
        $a = [];
        $c = 0;

        foreach ($tok as $t) {
            if ($t == '|' || $t == null) {    
                $newNode->chain[] = ['handler'=>$h, 'args'=>$a, 'argc'=>$c];
                $h = null;
                $a = [];
                $c = 0;
            }
            elseif (!$h) { $h = $t; } else { $a[] = $t; ++$c; }
        } 

        return $newNode;
    }

    function unparse($tree)
    {
        $stack = [(object)['n'=>$tree, 'i'=>0]];
        $stackIdx = 0;

        $out = '';
        $stackNode = $stack[$stackIdx];
        while (true) {
            $current = isset($stackNode->n->nodes[$stackNode->i]) 
                ? $stackNode->n->nodes[$stackNode->i] 
                : null
            ;
            if (!$current) {
                if ($stackIdx <= 0)
                    break;
                
                if ($stackNode->n->type == Renderer::P_BLOCK)
                    $out .= $stackNode->n->vc;
                $stackNode = $stack[--$stackIdx];
            }
            else {
                ++$stackNode->i;
                switch ($current->type) {
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
                        throw new \Exception("Cannot unparse ".Helper::tokenName($current->type)."({$current->type})");
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

class RenderException extends \RuntimeException
{
    function __construct($message='', $line=null)
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

function throw_on_error()
{
	static $set=false;
	if (!$set) {
		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			$reporting = error_reporting();
			if ($reporting > 0 && ($reporting & $errno)) {
				throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
			}
		});
		$set = true;
	}
}

}}
