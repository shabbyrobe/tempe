<?php
namespace Tempe\Ext;

class String
{
    function __construct()
    {
        $this->filters = [
            'upper'=>'strtoupper',
            'lower'=>'strtolower',
            'ucfirst'=>'ucfirst',
            'lcfirst'=>'lcfirst',
            'ucwords'=>'ucwords',

            'trim'=>'trim',
            'ltrim'=>'ltrim',
            'rtrim'=>'rtrim',

            'rev'=>'strrev',

            'nl2br'=>'nl2br',
            'striptags'=>'strip_tags',
            'base64'=>'base64_encode',
        ];
    }
}
