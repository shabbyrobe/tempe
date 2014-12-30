<?php
namespace Tempe;

class Parser
{
    const M_STRING = 1;
    const M_TAG = 2;

    const VERSION = 2;

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
                )?
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
        if (!is_array($tokens)) {
            $tokens = $this->tokenise($tokens);
        }

        $line = 1;
        $tree = (object)[
            'type'=>Renderer::P_ROOT,
            'id'=>null, 
            'nodes'=>[],
            'line'=>$line,
            'version'=>Parser::VERSION,
        ];
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

            if ($isNewline) {
                ++$line;
            }

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
                    throw new Exception\Parse("Unexpected $current at line $line");
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
                            if ($node->type != Renderer::P_BLOCK) {
                                throw new Exception\Parse("Block close found, but no block open", $line);
                            }
                            $id = trim($buffer);
                            if (($node->id || $id) && $node->id != $id) {
                                throw new Exception\Parse("Block close mismatch. Expected '{$node->id}', found '$id'", $line);
                            }
                            $node->vc = $tagString;

                            if ($this->lang) {
                                foreach ($node->chain as $pos=>$handler) {
                                    $this->lang->check($handler, $node, $pos);
                                }
                            }

                            $node = $stack[--$stackIdx];
                        }
                        else {
                            $newNode->v = $tagString;
                            $node->nodes[] = $newNode;
                            if ($this->lang) {
                                foreach ($newNode->chain as $pos=>$handler) {
                                    $this->lang->check($handler, $newNode, $pos);
                                }
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

        if ($currentMode == self::M_TAG) {
            throw new Exception\Parse("Tag close mismatch (opened on line $bufferLine)");
        }
        if ($node != $tree) {
            throw new Exception\Parse("Unclosed block '".($node->id ?: "(unnamed)")."'", $node->line);
        }
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
        if (!$ok) {
            throw new Exception\Parse('Invalid tag: '.$tagString, $bufferLine);
        }
        if (isset($m['oid'])) {
            $newNode->id = $m['oid'];
        }

        if (isset($m['ochain'])) {
            $tok = preg_split('~(?: \s* (\|) \s* | \s+ )~x', $m['ochain'], null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $tok[] = null;

            $h = null;
            $a = [];
            $c = 0;

            foreach ($tok as $t) {
                if ($t == '|' || $t == null) {    
                    $newNode->chain[] = (object) ['name'=>$h, 'args'=>$a, 'argc'=>$c];
                    $h = null;
                    $a = [];
                    $c = 0;
                }
                elseif (!$h) { $h = $t; } else { $a[] = $t; ++$c; }
            } 
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
                if ($stackIdx <= 0) {
                    break;
                }
                if ($stackNode->n->type == Renderer::P_BLOCK) {
                    $out .= $stackNode->n->vc;
                }
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
                    throw new Exception\Parse("Cannot unparse ".Helper::nodeName($current->type)."({$current->type})");
                }
            }
        }

        return $out;
    }
}
