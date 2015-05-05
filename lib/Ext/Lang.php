<?php
namespace Tempe\Ext;

class Lang
{
    public $allowUnsetKeys = true;

    function __construct($options=[])
    {
        if (isset($options['allowUnsetKeys'])) {
            $this->allowUnsetKeys = $options['allowUnsetKeys'] == true;
            unset($options['allowUnsetKeys']);
        }

        $blocks = ['if'=>true, 'not'=>true, 'each'=>true, 'block'=>true, 'push'=>true, 'set'=>true];
        if (isset($options['blocks'])) {
            if ($options['blocks']) {
                $blocks = $options['blocks'] + $blocks;
            }
            else {
                foreach ($blocks as &$v) {
                    $v = false;
                }
            }
            unset($options['blocks']);
        }

        $this->blockHandlers = [];
        
        if ($blocks['if']) {
            $id = $blocks['if'] === true ? 'if' : $blocks['if'];
            $this->blockHandlers[$id] = function(&$scope, $key, $renderer, $node) {
                if (isset($scope[$key]) && $scope[$key]) {
                    return $renderer->renderTree($node, $scope);
                }
            };
        }

        if ($blocks['not']) {
            $id = $blocks['not'] === true ? 'not' : $blocks['not'];
            $this->blockHandlers[$id] = function(&$scope, $key, $renderer, $node) {
                if (!isset($scope[$key]) || !$scope[$key]) {
                    return $renderer->renderTree($node, $scope);
                }
            };
        }

        if ($blocks['each']) {
            $id = $blocks['each'] === true ? 'each' : $blocks['each'];
            $this->blockHandlers[$id] = function(&$scope, $key, $renderer, $node) {
                if (!isset($scope[$key])) {
                    if (!$this->allowUnsetKeys) {
                        throw new \Tempe\RenderException("Unknown variable $key");
                    } else {
                        return;
                    }
                }

                $out = '';
                $idx = 0;
                foreach ($scope[$key] as $key=>$item) {
                    $kv = ['_key_'=>$key, '_value_'=>$item, '_first_'=>$idx == 0, '_idx_'=>$idx, '_num_'=>$idx+1];
                    $curScope = is_array($item) ? array_merge($scope, $item, $kv) : $kv;
                    $out .= $renderer->renderTree($node, $curScope);
                    $idx++;
                }
                return $out;
            };
        }

        if ($blocks['block']) {
            $id = $blocks['block'] === true ? 'block' : $blocks['block'];
            $this->blockHandlers[$id] = function(&$scope, $key, $renderer, $node) {
                $out = $renderer->renderTree($node, $scope);
                if ($key) {
                    $scope[$key] = $out;
                } else {
                    return $out;
                }
            };
        }

        if ($blocks['set']) {
            $id = $blocks['set'] === true ? 'set' : $blocks['set'];
            $this->blockHandlers[$id] = function(&$scope, $key, $renderer, $node) {
                $out = $renderer->renderTree($node, $scope);
                if (!$key) {
                    throw new \Tempe\RenderException("Set requires key");
                }
                $scope[$key] = $out;
            };
        }

        if ($blocks['push']) {
            $id = $blocks['push'] === true ? 'push' : $blocks['push'];
            $this->blockHandlers[$id] = function(&$scope, $key, $renderer, $node) {
                if (!isset($scope[$key])) {
                    if (!$this->allowUnsetKeys) {
                        throw new \Tempe\RenderException("Unknown variable $key");
                    } else {
                        return;
                    }
                }

                $newScope = $scope;
                $item = $scope[$key];
                $newScope = $item + $newScope;
                return $renderer->renderTree($node, $newScope);
            };
        }

        $this->valueHandlers = [
            'get'=>function(&$scope, $key) {
                if (isset($scope[$key])) {
                    return $scope[$key];
                } elseif (!$this->allowUnsetKeys) {
                    throw new \Tempe\RenderException("Unknown variable $key");
                }
            },
        ];

        // support tempe 1 to 2 bridge
        $this->valueHandlers['=']   = $this->valueHandlers['get'];

        if ($options) {
            throw new \InvalidArgumentException("Unknown options: ".implode(', ', array_keys($options)));
        }
    }
}
