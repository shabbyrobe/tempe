<?php
namespace Tempe\Ext;

class Lang
{
    public $allowUnsetKeys = true;

    function __construct($options=[])
    {
        $ext = $this;

        if (isset($options['allowUnsetKeys'])) {
            $this->allowUnsetKeys = $options['allowUnsetKeys'] == true;
            unset($options['allowUnsetKeys']);
        }

        $blocks = ['if'=>true, 'not'=>true, 'each'=>true, 'block'=>true, 'push'=>true];
        if (isset($options['blocks'])) {
            if ($options['blocks']) {
                $blocks = $options['blocks'] + $blocks;
            }
            else {
                foreach ($blocks as &$v) $v = false;
            }
            unset($options['blocks']);
        }

        $this->blockHandlers = [];
        
        if ($blocks['if']) {
            $this->blockHandlers['if'] = function(&$scope, $key, $contentTree) {
                if (isset($scope[$key]) && $scope[$key])
                    return $this->renderTree($contentTree, $scope);
            };
        }

        if ($blocks['not']) {
            $this->blockHandlers['not'] = function(&$scope, $key, $contentTree) {
                if (!isset($scope[$key]) || !$scope[$key])
                    return $this->renderTree($contentTree, $scope);
            };
        }

        if ($blocks['each']) {
            $this->blockHandlers['each'] = function(&$scope, $key, $contentTree) use ($ext) {
                if (!$ext->allowUnsetKeys && !isset($scope[$key]))
                    throw new \Tempe\RenderException("Unknown variable $key");

                $out = '';
                $idx = 0;
                foreach ($scope[$key] as $key=>$item) {
                    $kv = ['@key'=>$key, '@value'=>$item, '@first'=>$idx == 0, '@idx'=>$idx, '@num'=>$idx+1];
                    $curScope = is_array($item) ? array_merge($scope, $item, $kv) : $kv;
                    $out .= $this->renderTree($contentTree, $curScope);
                    $idx++;
                }
                return $out;
            };
        }

        if ($blocks['block']) {
            $this->blockHandlers['block'] = function(&$scope, $key, $contentTree) {
                $out = $this->renderTree($contentTree, $scope);
                if ($key)
                    $scope[$key] = $out;
                else
                    return $out;
            };
        }

        if ($blocks['push']) {
            $this->blockHandlers['push'] = function(&$scope, $key, $contentTree) use ($ext) {
                if (!isset($scope[$key]) && !$ext->allowUnsetKeys)
                    throw new \Tempe\RenderException("Unknown variable $key");

                $newScope = $scope;
                $item = $scope[$key];
                $newScope = $item + $newScope;
                return $this->renderTree($contentTree, $newScope);
            };
        }

        $this->varHandlers = [
            '='=>function(&$scope, $key) use ($ext) {
                if (isset($scope[$key]))
                    return $scope[$key];
                elseif (!$ext->allowUnsetKeys)
                    throw new \Tempe\RenderException("Unknown variable $key");
            },
        ];

        if ($options) {
            throw new \InvalidArgumentException("Unknown options: ".implode(', ', array_keys($options)));
        }
    }
}
