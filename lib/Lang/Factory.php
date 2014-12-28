<?php
namespace Tempe\Lang;

class Factory
{
    private function __construct()
    {}

    public static function createBasic()
    {
        return (new Basic())->addPart(new Part\Core);
    }
}
