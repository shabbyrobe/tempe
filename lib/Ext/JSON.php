<?php
namespace Tempe\Ext;

class JSON
{
    function __construct()
    {
        $this->blockHandlers['json'] = function(&$scope, $key, $renderer, $node) {
            $text = $renderer->renderTree($node, $scope);
            $data = json_decode($text, !!'assoc');
            if ($data === null && ($err = json_last_error())) {
                throw new \Tempe\RenderException("Could not parse json on line ".$node->l);
            }
            $scope = array_merge($scope, $data);
        };
    }
}
