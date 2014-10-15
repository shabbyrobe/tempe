<?php
namespace Tempe;

use Tempe\Renderer;

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
        $tree = (object)['t'=>Renderer::P_ROOT, 'c'=>[], 'l'=>$line];
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
                        $node->c[] = (object)['t'=>Renderer::P_ESC, 'l'=>$line, 'v'=>$current];
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
                case '{;': case '{{': case '{{#': case '{{/':
                    throw new ParseException("Unexpected $current at line $line");
                break;

                case '}}':
                    $tagString = "{{{$tagType}{$buffer}}}";

                    $chain = [];
                    foreach (explode('|', $buffer) as $call) {
                        if (preg_match('~^
                            \s*
                            (
                            (?P<handler>'.static::$identifierPattern.')
                            (\s+(?P<key>\@?'.static::$identifierPattern.'))?
                            )?
                            \s*~x',
                            $call,
                            $cm)
                        ) {
                            if (isset($cm['handler']))
                                $chain[] = (object)['h'=>$cm['handler'], 'k'=>isset($cm['key']) ? $cm['key'] : null];
                        }
                        else {
                            throw new ParseException("Invalid call '{$call}' on line {$bufferLine}");
                        }
                    }

                    if ($tagType == '#') {
                        $node = $node->c[] = (object)[
                            't'=>Renderer::P_BLOCK, 'hc'=>$chain,
                            'c'=>[], 'l'=>$bufferLine, 'vo'=>$tagString,
                        ];
                        $stack[++$stackIdx] = $node;
                    }
                    elseif ($tagType == '/') {
                        if ($node->t != Renderer::P_BLOCK)
                            throw new ParseException();

                        $node->vc = $tagString;

                        if ($node->hc) {
                            $open = $node->hc[0];
                            $close = isset($chain[0]) ? $chain[0] : null;
                            if (isset($chain[1]))
                                throw new ParseException("Only the first handler is valid in block close on line {$line}");

                            if (!$close) {
                                throw new ParseException(
                                    "Handler close mismatch on line {$line}. Expected {$open->h}, found (unnamed)"
                                );
                            }
                            elseif ($open->h != $close->h) {
                                throw new ParseException(
                                    "Handler close mismatch on line {$line}. Expected {$open->h}, found {$close->h}"
                                );
                            }
                            if ($close->k && $close->k != $open->k) {
                                throw new ParseException(
                                    "Handler key close mismatch on line {$line}. Expected {$open->k}, found {$close->k}"
                                );
                            }
                        }

                        $node = $stack[--$stackIdx];
                    }
                    else {
                        $node->c[] = (object)[
                            't'=>Renderer::P_VALUE, 'hc'=>$chain, 
                            'l'=>$bufferLine, 'v'=>$tagString,
                        ];
                    }
                    $buffer = '';
                    $bufferLine = $line;
                    $currentMode = self::M_STRING;
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
            throw new ParseException("Unclosed block ".(isset($node->hc[0]) ? "'{$node->hc[0]->h}({$node->hc[0]->k})'" : "(unnamed)" )." on line {$node->l}");

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

class ParseException extends \RuntimeException
{}

