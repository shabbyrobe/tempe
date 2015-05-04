<?php
namespace Tempe;

use Tempe\Renderer;

class Parser
{
    const M_STRING = 1;
    const M_TAG = 2;

    public static $identifierPattern = '[a-zA-Z\d_]([a-zA-Z_\/\.\-\d]*[a-zA-Z\d_])*';
    public static $filterPattern     = '[a-zA-Z\d][a-zA-Z\d]*';

    function tokenise($in)
    {
        $pattern = '~( \{; | \{\{ | \}\} | \r\n | \n | \r )~x';

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
        $tree = (object)['t'=>Renderer::P_ROOT, 'c'=>[], 'l'=>$line];
        $node = $tree;
        $stack = [$node];
        $stackIdx = 0;

        $i = 0;
        $tokenLen = count($tokens);

        $currentMode = self::M_STRING;

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
                if ($current == '{;' || $current == '{{') {
                    if ($buffer) {
                        $node->c[] = (object)['t'=>Renderer::P_STRING, 'v'=>$buffer, 'l'=>$bufferLine];
                        $bufferLine = $line;
                    }
                    if ($current == '{{') {
                        $buffer = $current;
                        $currentMode = self::M_TAG;
                    }
                    else {
                        $buffer = '';
                        $node->c[] = (object)['t'=>Renderer::P_ESC, 'l'=>$line, 'v'=>$current];
                    }
                }
                else {
                    $buffer .= $current;
                }
            break;

            case self::M_TAG:
                $buffer .= $current;

                switch ($current) {
                case '{;':
                case '{{':
                    throw new ParseException("Unexpected $current at line $line");

                case '}}':
                    if (!preg_match(
                        '~^\{\{
                        (?P<type>[\#/])?
                        \s*
                        (?:
                            (?P<handler>(=|('.static::$identifierPattern.')))
                            (\s+(?P<key>'.static::$identifierPattern.'))?
                            (?P<filters>(\s*\|(\s*'.static::$filterPattern.')+)+)?
                            \s*
                        )?
                        \}\}$~x', 
                        $buffer,
                        $match)
                    ) {
                        throw new ParseException("Invalid tag '{$buffer}' on line {$bufferLine}");
                    }

                    $key = isset($match['key']) ? $match['key'] : null;
                    $handler = isset($match['handler']) ? $match['handler'] : null;

                    $filters = isset($match['filters']) 
                        ? preg_split('~\s*\|\s*~', $match['filters'], null, PREG_SPLIT_NO_EMPTY) 
                        : []
                    ;
                    foreach ($filters as &$f) {
                        $f = preg_split('/\s+/', $f, 2); 
                    }

                    if (isset($match['type']) && $match['type']) {
                        if ($match['type'] == '#') {
                            $node = $node->c[] = (object)[
                                't'=>Renderer::P_BLOCK, 'h'=>$handler, 'k'=>$key,
                                'f'=>$filters, 'c'=>[], 'l'=>$bufferLine, 'vo'=>$match[0],
                            ];
                            $stack[++$stackIdx] = $node;
                        }
                        elseif ($match['type'] == '/') {
                            if (isset($match['filters'])) {
                                throw new ParseException("Handler close on line {$line} contained filters");
                            }
                            $node->vc = $match[0];
                            if ($handler != $node->h) {
                                throw new ParseException(
                                    "Handler close mismatch on line {$line}. Expected {$node->h}, found {$handler}"
                                );
                            }
                            if ($key && $key != $node->k) {
                                throw new ParseException(
                                    "Handler key close mismatch on line {$line}. Expected {$node->k}, found {$key}"
                                );
                            }
                            $node = $stack[--$stackIdx];
                        }
                        else {
                            throw new \Exception();
                        }
                    }
                    else {
                        $node->c[] = (object)[
                            't'=>Renderer::P_VALUE, 'h'=>$handler, 'k'=>$key, 
                            'f'=>$filters, 'l'=>$bufferLine, 'v'=>$match[0],
                        ];
                    }
                    $buffer = '';
                    $bufferLine = $line;
                    $currentMode = self::M_STRING;
                break;
                }

            break;
            }
        }

        if ($currentMode == self::M_TAG) {
            throw new ParseException("Tag close mismatch, open was on line $bufferLine");
        }
        if ($node != $tree) {
            throw new ParseException("Unclosed block {$node->h}({$node->k}) on line {$node->l}");
        }

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
                if ($stackIdx <= 0) {
                    break;
                }
                if ($stackNode->n->t == Renderer::P_BLOCK) {
                    $out .= $stackNode->n->vc;
                }
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

class ParseException extends \RuntimeException
{}

