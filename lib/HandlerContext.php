<?php
namespace Tempe;

class HandlerContext
{
    public $scope;
    public $chainPos = 0;
    public $break = false;
    public $node;
    public $renderer;
}
