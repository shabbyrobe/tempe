<?php
namespace Tempe;

interface Lang
{
    function check(array $handler, $node, $chainPos);
    function handle(array $handler, $in, HandlerContext $context);
}
