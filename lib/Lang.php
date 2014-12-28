<?php
namespace Tempe;

interface Lang
{
    function check($handler, $node, $chainPos);
    function handle($handler, $in, HandlerContext $context);
}
