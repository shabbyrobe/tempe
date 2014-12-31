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
    function check($handler, $node, $chainPos);
    function handle($handler, $in, HandlerContext $context);
    function handleEmpty(HandlerContext $context);
}
