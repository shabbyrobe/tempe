Tempe
=====

.. image:: https://travis-ci.org/shabbyrobe/tempe.svg

Tempe (temˈpē) is a very simple templating language.

It is named after the suburb of Sydney, which is where I was driving when I decided how I
wanted it to work. Inspirational story, eh!

It provides very simple primitives which can be composed into your own domain specific,
simple but feature-rich template language, and it also comes bundled with a pre-built
generic language which provides semantics which will appear somewhat familiar to users of
`handlebars.js <http://handlebarsjs.com/>`_.

And so, on to the canonical example. Using the basic web templating language, a typical Tempe
template::

    Hello {{= name | as.html}}
    You have just won {{= value}} dollars!
    {{#if in_ca}}
    Well, {{= taxed_value}} dollars, after taxes.
    {{/if in_ca}}

Given the following hash::

    {
      "name": "Chris",
      "value": 10000,
      "taxed_value": 10000 - (10000 * 0.4),
      "in_ca": true
    }

Will produce the following::

    Hello Chris
    You have just won 10000 dollars!
    Well, 6000 dollars, after taxes.


.. contents::


Why?
----

Mustache is great, but Mustache can stop you from doing all the things in life you'd like
to. Like those little bits of "logic" that aren't really quite logic but you still need
anyway. Context-specific escaping filters spring instantly to mind.

Tempe's core is also much simpler than Mustache - it comes with fewer features by default,
but the primitives it does provide make it possible to provide all of the same features.
Tempe comes with an implementation of these features you can use if you wish, though you
do not have to.

Because of this design, Tempe can also be used to create your own templating DSL using its
primitives - you can strip it back to almost nothing and tailor it to your needs. This
makes it ideal for scenarios where you do not want to provide a complex templating system
but need a little bit more than ``strtr`` can give you (which is exactly the scenario that
led me to write it in the first place - ``strtr`` was too simple, Mustache wasn't simple
enough).


Primitives
----------

There are three types of primitive in the Tempe language - **value tags**, **block
tags** and the **escape sequence**.

All tag primitives are opened with ``{{`` and closed with ``}}``. Unlike Mustache, this
cannot be changed.

Tags contain **identifiers** which are separated by whitespace or pipe characters (``|``),
and which must satisfy the following regex (with some minor exceptions, mentioned later)::

    [a-zA-Z\d_]([a-zA-Z_\.\-\d]*[a-zA-Z\d])*

Value tags look like this::

    {{ handler }}
    {{ handler key }}
    {{ handler | filter }}
    {{ handler key | filter | filter }}

Block tags look like this::

    {{# handler }}{{/ handler }}
    {{# handler key }}{{/ handler }}
    {{# handler key }}{{/ handler key }}
    {{# handler | filter }}{{/ handler }}
    {{# handler key | filter | filter }}{{/ handler }}

Whitespace inside tags between symbols is ignored. ``{{handler key|filter|filter}}`` is
identical to ``{{  handler  key  |  filter  |  filter  }}``

"Whitespace" is equivalent to the PCRE ``\s`` escape sequence (LF, CR, FF, HTAB, SPACE).

The escape sequence simply emits a curly brace and looks like this::

    {!

It allows you to include the tag opener (``{{``) in your output like so::

    {!{!

Whitespace-only tags and empty tags are allowed. This can be used for basic whitespace
control::

    {{}}
    {{
        }}
    {{#    }}{{/      }}

You can simulate template comments by using an empty block::

    {{#}}This will not appear{{/}}

.. warning::

    The canonical example in the introduction demonstrates the use of the value tag
    ``{{= key}}`` to place the value of ``key`` into the output. 
    
    ``{{=`` is **not** a Tempe primitive; ``=`` is actually a ``handler`` which is
    registered by the basic templating language extension.


Value Tags
~~~~~~~~~~

Value tags invoke a ``handler`` function which will be passed an optional ``key``.
The return value of the ``handler``  will be piped through each optional ``filter``
specified one after the other.

The resulting string will be appended to the output.

Assuming a handler ``echo`` is registered which returns the key exactly as passed, and the
filter ``x`` is registered which appends the string ``x`` to its input, the following
demonstrates the different ways a value tag can be invoked:

Template::

    1. {{echo}}
    2. {{echo foo}}
    3. {{echo foo | x}}
    4. {{echo foo | x | x}}
    5. {{echo | x | x}}
    6. {{ echo|x|x }}

Output::

    1. 
    2. foo
    3. foox
    4. fooxx
    5. xx
    6. xx


Block Tags
~~~~~~~~~~

Block tags invoke a ``handler`` function which will be passed the optional ``key`` and the
parse tree representing the ``contents``. The ``handler`` may invoke the renderer using
the contents, dispose of it, reverse it, eat it, whatever.

The return value of the ``handler`` will be piped through each optional ``filter``
specified one after the other.

The resulting string will be appended to the output.

Assuming the following things are registerd with the renderer:

- a block handler ``double`` which returns the key exactly as passed and then invokes
  the renderer with the contents twice,
- a value handler ``echo`` which returns the key exactly as passed,
- a filter ``x`` which appends the string ``x`` to its input

The following example demonstrates block tags:

Template::

    1. {{# double foo}} bar{{/double}}
    2. {{# double foo | x}} bar{{/ double}}
    3. {{# double | x}}bar {{/ double}}
    4. {{# double foo | x}}bar {{/ double foo}}

Output::

    1. foo bar bar 
    2. foo bar barx
    3. bar bar x
    4. bar bar x

The close tag can optionally contain the same key as the open tag. This key is checked to
see if it equals the key used in the open tag. The following are valid::

    {{# block key}}{{/block}}
    {{# block key}}{{/block key}}

The following are invalid::

    {{# block key}}{{/block yup}}
    {{# block}}{{/block key}}

The close tag can not contain filters. These should be included on the open tag. This is
invalid::

    {{# block key}}{{/block | pants}}


Escape Sequence
~~~~~~~~~~~~~~~

The escape sequence simply emits a curly brace and looks like this::

    {!

It allows you to include the tag opener (``{{``) in your output like so::

    {!{!

It contains no identifiers and allows no whitespace.

It is not necessary to escape a single curly brace except to disambiguate it from a tag
opening. The following does not require escaping::

    {"json": {"yep": {{= key | as.js }} }}

But this example does::

    {"json": {!{{= key | as.js }}: "yep" }}


Cut To The Chase. I Just Wanna Make Web Pages
---------------------------------------------

The simplest way to get started making web templates is to use the basic web language. You
get ``if``, ``each`` and ``=`` handlers for free (along with a few others), as well as the
String and Escaper extensions for good measure.

Instantiating is easy:

.. code-block:: php
    
    <?php
    $renderer = \Tempe\Renderer::createBasicWeb();

The basic language is made up of the following handlers:

- ``{{= key}}``: Echo the variable at ``key``
- ``{{# if key}}{{/if}}``: Conditionally display a block
- ``{{# not key}}{{/not}}``: Conditionally display a block (inverse ``if``)
- ``{{# each key}}{{/each}}``: Iterate over ``key``
- ``{{# block key}}{{/block}}``: Capture a block into ``key``, or filter a block's contents
- ``{{# push key}}{{push}}``: Push a scope onto the stack

Some basic filter sets are provided as well:

- Web output escapers (quoting for HTML, etc)
- String manipulation (``upper``, ``lower``, etc)

.. warning::

    *Tempe* does not do any escaping by default. It is incumbent on the template author to
    be aware of the context in which they are emitting values **at all times**.
    
    Pádraic Brady's article `Automatic Output Escaping in PHP and the Real Future of
    Preventing Cross-Site Scripting (XSS)
    <http://blog.astrumfutura.com/2012/06/automatic-output-escaping-in-php-and-the-real-future-of-preventing-cross-site-scripting-xss/>`_
    is essential reading for anyone who believes that automatic output escaping isn't a
    bad idea.


``{{= key}}``: Echo a variable
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Value handler which output the variable ``key`` from the current scope::

    {{= key}}

Example:

.. code-block:: php

    <?php
    $tmpl = "{{= foo}} {{= bar | upper}}";
    $vars = ['foo'=>'hello', 'bar'=>'world'];
    echo $renderer->render($tmpl, $vars);

Output::

    hello world


``{{#if key}}{{/if}}``: Conditionally display a block
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The ``if`` block handler will render its contents if the ``key`` is present and truthy in the
current scope::

    {{# if key}}Visible{{/if}}

Example:

.. code-block:: php
    
    <?php
    $tmpl = "
    {{# if yes     }} 1. Visible {{/if}}
    {{# if alsoYep }} 2. Visible {{/if}}
    {{# if nup     }} 3. Not visible {{/if}}
    {{# if unset   }} 4. Not visible {{/if}}
    ";
    $vars = [
        "yes"=>true,
        "alsoYes"=>"hello",
        "nup"=>false,
    ];
    echo $renderer->render($tmpl, $vars);

Output::

    1. Visible
    2. Visible


``{{#not key}}{{/not}}``: Conditionally display a block (inverse ``if``)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The ``not`` block handler is the opposite of the ``if`` handler - it will render its
contents if the key is not present in the current scope or evaluates to falsy::

    {{# not key}}Visible{{/not}}

Example:

.. code-block:: php
    
    <?php
    $tmpl = "
    {{# not yes     }} 1. Not Visible {{/not}}
    {{# not alsoYep }} 2. Not Visible {{/not}}
    {{# not nup     }} 3. Visible {{/not}}
    {{# not unset   }} 4. Visible {{/not}}
    ";
    $vars = [
        "yes"=>true,
        "alsoYes"=>"hello",
        "nup"=>false,
    ];
    echo $renderer->render($tmpl, $vars);

Output::

    3. Visible
    4. Visible


``{{# each key}}{{/each}}``: Iterate
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The ``each`` handler allows looping over an array::

    {{# each key}}{{= @value}}{{/each}}

The contents will be rendered once for each element in the array.

Example:

.. code-block:: php
    
    <?php
    $tmpl = "{{# each list}}var1 = {{= var1}}, var2 = {{= var2}}\n{{/each}}";
    $vars = [
        'list'=>[
            ['var1'=>'foo', 'var2'=>'bar'],
            ['var1'=>'baz', 'var2'=>'qux'],
        ],
    ];
    echo $renderer->render($tmpl, $vars);

Output::

    var1 = foo, var2 = bar
    var1 = baz, var2 = qux


The following metavariables are made available in the scope:

- ``@key`` -  The current array key
- ``@value`` - The current array value
- ``@first`` - Boolean indicating whether this is the first iteration
- ``@idx`` -  0-based numeric index of current iteration
- ``@num`` -  1-based numeric index of current iteration


A new scope is created which is popped when the block exits. If the list element is an
array, it is merged with the current scope:

.. code-block:: php

    <?php
    $tmpl = "{{= var }} {{# each list }} {{= var }} {{/each}} {{= var }}";
    $vars = [
        'var'=>'foo',
        'list'=>[['var'=>'bar'], ['var'=>'baz']],
    ];
    echo $renderer->render($tmpl, $vars);

Output::

    foo  bar  baz  foo


Joining strings
^^^^^^^^^^^^^^^

There is no ``join`` or ``implode`` function, but you can simulate joining simply by
checking if the element is ``#not`` the ``@first``:

.. code-block:: php

    <?php
    $tmpl = "{{# each list}}{{#not @first}}, {{/not}}{{= @value }}{{/each}}";
    $vars = [
        'list'=>['foo', 'bar', 'baz', 'qux'],
    ];
    echo $renderer->render($tmpl, $vars);

Output::

    foo, bar, baz, qux


``{{# block key}}{{/block}}``: Capture a block into a key, or filter a block's contents
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The ``block`` handler can do two things depending on whether a ``key`` is supplied.

With a ``key``, it captures the output of rendering the contents in to the current scope
using ``key`` as the name. Filters are ignored in this mode.

Without a ``key``, it simply echoes the output of rendering the contents, but filters will
be applied to the result.

.. code-block:: php

    <?php
    $tmpl = "
    Before capture: {{# block foo | upper}}hello{{/block}}
    After capture: {{= foo}}
    Filter: {{# block | upper}}hello{{/block}}
    ";
    echo $renderer->render($tmpl);

Output::

    Before capture:
    After capture: hello
    Filter: HELLO


``{{# push key}}{{push}}``: Push a scope onto the stack
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The ``push`` handler copies the current scope and merges it with the associative array
found at ``key``. This can be used to access nested elements.

The scope is popped when the block exits.

.. code-block:: php

    <?php
    $tmpl = 
        "{{#push first}}".
            "{{# push second}}".
                "{{= all}} {{= var}} ".
            "{{/ push}}".
            "{{= all}} {{= var}} ".
        "{{/ push}}".
        "{{= all}} {{= var}}"
    ;
    $vars = [
        'all'=>'z',
        'var'=>'a',
        'first'=>[
            'var'=>'b',
            'second'=>['var'=>'c'],
        ],
    ];
    echo $renderer->render($tmpl, $vars);

Output::

    c z b z a z


Web Escaping Filters
~~~~~~~~~~~~~~~~~~~~

Provided by ``Tempe\Filter\WebEscaper`` and loaded when using
``Tempe\Renderer:;createBasicWeb()``. Provides basic output escaping filters with a web
focus.

Each filter method should be used to represent the context of the output and should
*always come last in the filter sequence*

``| as.html``
    Inside an HTML element, i.e. ``<p>{{= foo | as.html}}</p>``.

``| as.htmlAttr``
    Inside a quoted (single or double) HTML attribute, i.e. 
    ``<div class="{{= foo | as.htmlAttr}}">``

``| as.urlQuery``
    Inside a URL. If the value returned by the handler is an associative array, it will be
    turned into a query string, i.e. ``foo=bar&baz=qux``. If it is a string, it will be
    ``%`` encoded.
    
    If the URL is intended to be output into an HTML document, you will need to chain it
    with one of the other escapers, i.e. ``<a href="page.html?foo={{= bar |
    as.urlQuery | as.htmlAttr}}">``

``| as.js``
    Inside a quoted (single or double) Javascript string.
    i.e. ``var foo = "foo {{= bar | as.js}} baz";``

``| as.htmlComment``
    Inside an HTML comment: ``<!-- {{= foo | as.htmlComment}} -->``

``| as.unquotedHtmlAttr``
    Inside an unquoted HTML attribute: ``<a href={{= foo | as.unquotedHtmlAttr}} class=foo>``


String Filters
~~~~~~~~~~~~~~

Provided by ``Tempe\Filter\String``.

The following filters are made available by default:

- ``upper`` - Convert to upper case
- ``lower`` - Convert to lower case
- ``ucfirst`` - Convert the first character to upper case
- ``lcfirst`` - Convert the first character to lower case
- ``ucwords`` - Title Case All Words Just Like This Sentence
- ``trim`` - Trim leading and trailing whitespace
- ``ltrim`` - Trim leading whitespace
- ``rtrim`` - Trim trailing whitespace
- ``rev`` - Reverse the string
- ``nl2br`` - Convert newlines to ``<br/>``
- ``striptags`` - Remove any HTML tags. Uses `strip_tags() <http://php.net/strip_tags>`_

