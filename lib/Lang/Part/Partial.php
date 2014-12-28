<?php
namespace Tempe\Lang\Part;

use Tempe\Exception;

class Partial
{
    private $paths = [];

    function __construct($options)
    {
        if (!isset($options['paths']) || !is_array($options['paths']))
            throw new \InvalidArgumentException("Paths missing or not array");

        $this->paths = isset($options['paths']) ? $options['paths'] : [];

        $this->rules = [
            'tpl'     => ['argMin'=>0, 'argMax'=>1, 'check'=>[$this, 'check']],
            'incl'    => ['argMin'=>0, 'argMax'=>1, 'check'=>[$this, 'check']],
        ];

        $this->handlers = [
            'tpl'     => [$this, 'tpl'],
            'incl'    => [$this, 'incl'],
        ];
    }

    private function resolveFile($file)
    {
        if (!$file)
            throw new \InvalidArgumentException("No file passed");

        // Love to use realpath here, but it doesn't work with stream wrappers.
        // Bad PHP!
        if (strpos($file, '..') !== false)
            throw new \InvalidArgumentException("Invalid file $file");

        $parts = explode("/", $file, 2);
        if (!isset($parts[1]))
            throw new \InvalidArgumentException("Missing alias in $file");

        list ($alias, $path) = $parts;
        if (!isset($this->paths[$alias]))
            throw new \InvalidArgumentException("Unknown alias $alias");

        $basePath = $this->paths[$alias];
        $fullPath = "{$basePath}/{$path}";
        if (!file_exists($fullPath))
            throw new \InvalidArgumentException("Could not load $fullPath");

        return $fullPath;
    }

    function check($handler, $node, $chainPos)
    {
        // can't check dynamic partial
        if (!isset($handler['args'][0])) {
            if ($chainPos == 0)
                throw new Exception\Check("Partial without key cannot be first in a chain", $node->line);
        }
        else {
            $key = $handler['args'][0];
            try {
                $file = $this->resolveFile($key);
            }
            catch (\Exception $ex) {
                throw new \Tempe\Exception\Check("Could not resolve {$handler['handler']} '$key': {$ex->getMessage()}", $node->line, null, $ex);
            }
            
            if (!file_exists($file)) {
                throw new \Tempe\Exception\Check("{$handler['handler']} failed: File $key not found", $node->line, null, $ex);
            }
        }
        return true;
    }

    function tpl($in, $context)
    {
        $key = isset($context->args[0]) ? $context->args[0] : $in;

        return $context->renderer->render(
            file_get_contents($this->resolveFile($key)), 
            $context->scope
        );
    }

    function incl($in, $context)
    {
        $key = isset($context->args[0]) ? $context->args[0] : $in;

        return file_get_contents($this->resolveFile($key));
    }
}
