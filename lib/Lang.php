<?php
namespace Tempe;

/**
 * $handler = (object) [
 *     'name' => 'foo',
 *     'args' => ['arg1', 'arg2', 'arg3'],
 *     'argc' => 3,
 * ]:
 */
interface Lang
{
    /**
     * @param object $handler
     * @param object $node
     * @param int $chainPos
     * 
     * @throws Tempe\Exception\Check  If the check fails
     * @return bool  Must return true if the check passes
     */
    function check($handler, $node, $chainPos);

    /**
     * @param object $handler
     * @param mixed $in  Output of the previous handler in the 
     *                   chain, or '' if the first.
     */
    function handle($handler, $in, \Tempe\HandlerContext $context);

    /**
     * Will be called if the handler is empty, i.e.:
     *     {= }
     *     {# }{/}
     */
    function handleEmpty(\Tempe\HandlerContext $context);
}
