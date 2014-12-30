<?php
namespace Tempe;

class HandlerContext
{
    public $scope;
    public $chainPos = 0;
    public $break = false;
    public $recurse = true;
    public $args;
    public $argc;
    public $handler;
    public $node;
    public $renderer;
}
