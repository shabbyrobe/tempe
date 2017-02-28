<?php
namespace Tempe\Filter;

class WebEscaper
{
    public $charset = 'UTF-8';

    public function __construct($options=[])
    {
        if (isset($options['charset'])) {
            $this->charset = $charset ?: 'UTF-8';
            unset($options['charset']);
        }
        if ($options) {
            throw new \InvalidArgumentException("Unknown options: ".implode(', ', array_keys($options)));
        }
    }

    public function urlQuery($input)
    {
        if (is_array($input)) {
            return http_build_query($input, null, '&');
        }
        else {
            return rawurlencode($input);
        }
    }

    public function html($string)
    {
        $out = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $this->charset);

        if ($string && !$out)
            throw new \InvalidArgumentException("Escaping $string failed");

        return $out;
    }

    /**
     * The output context of this function MUST be a quoted HTML attribute.
     *
     * Valid:
     *   <a href="<?= $e->htmlAttr($string) ?>" />
     *   <a href='<?= $e->htmlAttr($string) ?>' />
     *
     * Very invalid (use htmlAttrUnquoted instead):
     *   <a href=<?= $e->htmlAttr($string) ?> />
     */
    public function htmlAttr($string)
    {
        // http://wonko.com/post/html-escaping
        $html = $this->html($string);

        $out = strtr($html, [
            "\0"=>'',
            '`'=>'&#96;',
        ]);

        return $out;
    }

    /**
     * Emits a string suitable for use anywhere within an HTML5 comment
     *
     * See http://dev.w3.org/html5/markup/syntax.html#comments
     */
    public function htmlComment($string)
    {
        // the spec is ambiguous about leading or trailing whitespace,
        // so this will remove any number of trailing dashes (i.e. if
        // $string = "- - - - - ", the result will be an empty string.
        $string = trim($string);
        if (!$string)
            return;

        $string = preg_replace_callback(
            "/-+/",
            function($match) { return trim(str_repeat("- ", strlen($match[0]))); },
            $string
        );
        $string = preg_replace("/(^>|<!|^->|[-\s]*-[-\s]*$)/", '', $string);
        return $string;
    }

    public function xml($string)
    {
        $out = htmlspecialchars($string, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, $this->charset);

        if ($string && !$out)
            throw new \InvalidArgumentException("Escaping $string failed");

        return $out;
    }

    public function xmlAttr($string)    { return $this->xml($string); }
    public function xmlComment($string) { return $this->htmlComment($string); }

    /**
     * http://mathiasbynens.be/notes/css-escapes
     * Based on this (required for expedience, need to verify it's good enough):
     * https://github.com/coverity/coverity-security-library/blob/develop/coverity-escapers/src/main/java/com/coverity/security/Escape.java
     */
    public function cssString($string)
    {
        if (!$string)
            return $string;

        return strtr($string, [
            // control chars
            "\b" => "\\08 ",
            "\t" => "\\09 ",
            "\n" => "\\0A ",
            "\f" => "\\0C ",
            "\r" => "\\0D ",

            // string chars
            "'"  => "\\'",
            '"'  => "\\\"",
            '\\' => "\\\\",

            // these are a bit too paranoid for us
            // html chars for closing the parent context
            // '&'  => "\\26 ",
            // '/'  => "\\2F ",
            // '<'  => "\\3C ",
            // '>'  => "\\3E ",

            "\u2028" => "\\002028 ",
            "\u2029" => "\\002029 ",
        ]);
    }

    /** @deprecated */
    public function unquotedHtmlAttr($string)
    {
        return $this->htmlAttrUnquoted($string);
    }

    /**
     * The output context of this function MUST be an unquoted HTML attribute.
     *
     * Valid:
     *   <a href=<?= $e->unquotedHtmlAttr($string) ?> />
     *
     * Very invalid (use htmlAttr instead):
     *   <a href="<?= $e->htmlAttr($string) ?>" />
     */
    public function htmlAttrUnquoted($string)
    {
        // http://wonko.com/post/html-escaping
        $html = $this->html($string);

        $out = strtr($html, [
            "\0"=>'',
            '`'=>'&#96;',
            '@'=>'&#64;',
            ' '=>'&#32;',
            '!'=>'&#33;',
            '$'=>'&#36;',
            '%'=>'&#37;',
            '('=>'&#40;',
            ')'=>'&#41;',
            '='=>'&#61;',
            '+'=>'&#43;',
            '{'=>'&#123;',
            '|'=>'&#124;',
            '}'=>'&#125;',
            '['=>'&#91;',
            ']'=>'&#93;',
        ]);

        return $out;
    }

    /** @deprecated */
    public function quotedJs($value)
    {
        return $this->jsQuoted($value);
    }

    /**
     * Returns a quoted, escaped string suitable for building javascript.
     *
     * for e.g.:
     *     let foo = <?= $e->jsQuoted("foo") ?>;
     *
     * will become:
     *     let foo = "foo";
     */
    public function jsQuoted($value)
    {
        if ($value === false || $value === null) {
            return '""';
        }

        if (is_string($value) || is_bool($value)) {
            return $this->jsonEncode($value);
        }
        elseif (is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return $this->jsonEncode((string) $value);
        }

        throw new \InvalidArgumentException("Unescapable type: ".\DebugHelper::getType($value));
    }

    /**
     * Returns an escaped string suitable for use within a javascript string
     *
     * for e.g.:
     *     let foo = "foo <?= $e->js('bar "bar" bar') ?>";
     *
     * will become:
     *     let foo = "foo bar \"bar\" bar";
     */
    public function js($string)
    {
        if ($string === '' || $string === false || $string === null)
            return "";

        // this also applies __toString()
        $string = (string) $string;
        if (is_string($string)) {
             $encoded = substr($this->jsonEncode($string), 1, -1);

            // json_encode only escapes double-quotes. escape singles by hand instead
            // of JSON_HEX_APOS in case old handsets don't like \u0027 (still need to
            // verify)
             return strtr($encoded, [
                 "'"=>"\\'",
             ]);
        }

        throw new \InvalidArgumentException("String or number required. Found: ".\DebugHelper::getType($string));
    }

    public function __invoke($value, $escapers)
    {
        return call_user_func_array([$this, 'multi'], func_get_args());
    }

    public function multi($value, $escapers)
    {
        if (!is_array($escapers)) {
            $escapers = array_slice(func_get_args(), 1);
        }

        foreach ($escapers as $e) {
            $value = call_user_func([$this, $e], $value);
        }

        return $value;
    }

    private function jsonEncode($scalar, $options=0)
    {
        $enc = json_encode($scalar, JSON_UNESCAPED_SLASHES | $options);

        // http://stackoverflow.com/questions/1580647/json-why-are-forward-slashes-escaped
        $enc = str_replace('</', '<\/', $enc);

        return $enc;
    }
}

