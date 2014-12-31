<?php
namespace Tempe\Lang;

class Factory
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {}

    public static function createBasic()
    {
        return (new Basic())
            ->addPart(new Part\Core)
            ->addPart(new Part\Strings);
    }

    public static function createDefault()
    {
        return (new Basic())
            ->addPart(new Part\Core)
            ->addPart(new Part\Strings);
    }
}
