<?php
namespace Tempe\Ext;

class Partial
{
    private $paths = [];

    function __construct($options)
    {
        if (!isset($options['paths']) || !is_array($options['paths'])) {
            throw new \InvalidArgumentException("Paths missing or not array");
        }

        $this->paths = isset($options['paths']) ? $options['paths'] : [];

        $this->valueHandlers = [
            'tpl'=>[$this, 'tpl'],
            'tplvar'=>[$this, 'tplvar'],
            'incl'=>[$this, 'incl'],
            'inclvar'=>[$this, 'inclvar'],
        ];
    }

    private function loadFile($file)
    {
        if (!$file) {
            throw new \InvalidArgumentException("No file passed");
        }

        // Love to use realpath here, but it doesn't work with stream wrappers.
        // Bad PHP!
        if (strpos($file, '..') !== false) {
            throw new \InvalidArgumentException("Invalid file $file");
        }

        $parts = explode("/", $file, 2);
        if (!isset($parts[1])) {
            throw new \InvalidArgumentException("Missing alias in $file");
        }

        list ($alias, $path) = $parts;
        if (!isset($this->paths[$alias])) {
            throw new \InvalidArgumentException("Unknown alias $alias");
        }

        $basePath = $this->paths[$alias];
        $fullPath = "{$basePath}/{$path}";
        if (!file_exists($fullPath)) {
            throw new \InvalidArgumentException("Could not load $fullPath");
        }

        return file_get_contents($fullPath);
    }

    function tpl(&$scope, $key, $renderer)
    {
        return $renderer->render($this->loadFile($key), $scope);
    }

    function tplvar(&$scope, $key, $renderer)
    {
        $key = isset($scope[$key]) ? $scope[$key] : null;
        return $renderer->render($this->loadFile($key), $scope);
    }

    function incl(&$scope, $key, $renderer)
    {
        return $this->loadFile($key);
    }

    function inclvar(&$scope, $key, $renderer)
    {
        $key = isset($scope[$key]) ? $scope[$key] : null;
        return $this->loadFile($key);
    }
}
