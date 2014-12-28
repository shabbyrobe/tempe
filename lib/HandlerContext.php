<?php
namespace Tempe;

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
