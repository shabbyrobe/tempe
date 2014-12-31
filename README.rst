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

If you want a simple, flexible templating engine comparable to Mustache/Handlebars or
Twig/Jinja2, see :ref:`language-quickstart`.

If you want to make your own simple, domain-specific templating language using Tempe's
primitives, see :ref:`guts-quickstart`.


Why?
----

The usual reason: dissatisfaction with the existing options.

`Mustache <http://mustache.github.io/>`_ is great, but Mustache can stop you from doing
all the things in life you'd like to. Like those little bits of "logic" that aren't really
quite logic but you still need anyway. Context-specific escaping filters spring instantly
to mind.

`Twig <http://twig.sensiolabs.com/>`_ is also great, but boy is it ever slow. Complex
templates can also be extremely cumbersome to work with due to how easy it is to allow
convoluted expression logic to bleed in to the template from your controller, and very
easily fall prey to ill-disciplined developers (I'm looking at you, everybody, and
especially at you, clock).

Both of these templating engines are fine choices, but I've spent too long bumping up
against the problems with both approaches. Tempe is an experiment in finding a
middle-ground. Remove the worst of the messy logic from Twig, keep the primitives as
simple as they can possibly be like Mustache.

Tempe's Guts are also much simpler than both Mustache and Twig - it comes with no features
by default, but the primitives it does provide make it possible to provide a
feature-complete replacement for either. Tempe comes bundled with an implementation of
these features you can use if you wish, though you do not have to.

Because of this design, Tempe can also be used to create your own templating DSL using its
Guts - you can strip it back to almost nothing and tailor it to your needs. This
makes it ideal for scenarios where you do not want to provide a complex templating system
but need a little bit more than ``strtr`` can give you (which is exactly the scenario that
led me to write it in the first place - ``strtr`` was too simple, Twig wasn't simple
enough, Mustache is too HTML-specific).


Primitives
----------

There are three types of primitive in Tempe - **value tags**, **block tags** and the
**escape sequence**.


Tags
~~~~

All tags are opened with ``{{`` and closed with ``}}``. This cannot be changed.

Value tags are intended to be wholly substituted and look like this::

    {{ chain }}

Block tags are used to surround and capture template parts::

    {{# chain }}contents{{/ }}

Block tags can be nested to an arbitrary depth::

    {{# chain }}{{# chain }}{{/ }}{{/ }}

Blocks can be named to make closing tags easier to identify::

    {{# b1: chain }} {{# b2: chain }} {{/ b2 }} {{/ b1 }}


Handler Chain
~~~~~~~~~~~~~

Both block and value tags MAY contain a `chain` of `handlers`.

Handler chains are similar to Unix pipelines - the output of one handler is sent to the
input of the next. The last handler in the chain connects to the renderer's output.

Each handler is separated by a pipe. You can chain as many handlers together as you wish::

    {{ handler | handler | handler }}

A handler is made up of one or more **identifiers**. Identifiers must satisfy the
following regex::

    [a-zA-Z_\/\.\-\d]+

The first identifier is considered the handler name, all subsequent identifiers are
considered arguments::

    {{ handler1 arg1 arg2 | handler2 arg1 arg2 }}

Whitespace inside tags between identifiers and pipes is ignored. The following tags are
identical::

    {{handler1 arg1 arg2|handler2|handler3}}
    {{  handler1   arg1   arg2  |  handler2  |  handler3  }}
    {{  handler1 arg1 arg2 | 
        handler2  |  handler3  }}

"Whitespace" is equivalent to the PCRE ``\s`` escape sequence (LF, CR, FF, HTAB, SPACE).

Whitespace-only tags and empty tags are allowed. This can be used for basic whitespace
control::

    {{}}
    {{
        }}
    {{#    }}{{/      }}

You can simulate template comments by using an empty block. This does not affect the
parser, only the renderer::

    {{#}}This will not appear{{/}}


Escape Sequence
~~~~~~~~~~~~~~~

The escape sequence simply emits a curly brace and looks like this::

    {;

It allows you to include the tag opener (``{{``) in your output like so::

    {;{;

It is not necessary to escape a single curly brace except to disambiguate it from a tag
opening. The following does not require escaping::

    {"json": {"yep": {{= key | as.js }} }}

But this example does::

    {"json": {;{{= key | as.js }}: "yep" }}


.. _language-quickstart:

The Language Quickstart
-----------------------

Get the variable ``foo`` and write to the output::

    {{ var foo }}

Get the variable ``foo``, escape as HTML then write to the output::

    {{ var foo | as html }}

.. warning::

    *Tempe* does not do any escaping by default. It is incumbent on the template author to
    be aware of the context in which they are emitting values **at all times**.
    
    Pádraic Brady's article `Automatic Output Escaping in PHP and the Real Future of
    Preventing Cross-Site Scripting (XSS)
    <http://blog.astrumfutura.com/2012/06/automatic-output-escaping-in-php-and-the-real-future-of-preventing-cross-site-scripting-xss/>`_
    is essential reading for anyone who believes that automatic output escaping isn't a
    bad idea.

Nested lookup::
    
    Given the hash {"foo": {"bar": "yep"}}
    This should print "yep": {{ var foo | var bar }}

Display a block if variable ``foo`` is truthy::

    {{# var foo | show }}Truthy!{{/}}

Display a block if variable ``foo`` is equal to ``hello``::

    {{# var foo | eqval hello | show }}Hello!{{/}}

Display a block if variable ``foo`` is equal to variable ``bar``::

    {{# var foo | eqvar bar | show }}foo is equal to bar!{{/}}

Display a block if variable ``foo`` is not equal to ``hello``::

    {{# var foo | eqval hello | not | show }}Goodbye!{{/}}

Block iteration::

    With the following hash:
    {"foo": [ {"a": 1, "b": 2}, {"a": 3, "b": 4} ]}

    This template:
    {{# each foo }}
        Key:            {{ var _key_ }}
        Value:          {{ var _value_ | var a }}
        0-based index:  {{ var _idx_ }}
        1-based number: {{ var _num_ }}
        Is it first?:   {{#var _first_|show}}Yep!{{/}}{{#var _first_|not|show}}Nup!{{/}}

        `foo` is merged with the current scope:
            {{ var a }}, {{ var b }}
    {{/}}

    Will output:

        Key:            0
        Value:          1
        0-based index:  0
        1-based number: 1
        Is it first?:   Yep!

        ``foo`` is merged with the current scope:
            1, 2
    
        Key:            1
        Value:          3
        0-based index:  1
        1-based number: 2
        Is it first?:   Nup!

        ``foo`` is merged with the current scope:
            3, 4

Set a variable to the contents of a block::

    Should print nothing: {{# set foo }}Hello World{{/}}
    Should print "Hello World": {{ var foo }}

Set a variable from a different variable::

    {{# set foo }}hello{{/}}
    {{# set bar }}world{{/}}
    {{ var foo | set bar }}
    {{# if foo | eqvar bar }}This should show!{{/}}

Push an array onto the current scope for a block::

    Given the hash:   {"foo": {"bar": "hello"}}
    The template:     {{# push foo }}{{ var bar }}{{/}}
    Should output:    hello

Build a nested array using ``push``::

    {{# a: push foo }}
    {{# b: push bar }}
    {{# set baz }}hello{{/}}
    {{/ b }}
    {{/ a }}
    Should print 'hello': {{ var foo | var bar | var baz }}

Handlers are chainable. This contrived example makes an entire block upper case, then html
escapes it, then sets it to another variable::

    {{# show | upper | as html | set foo }}
    foo & bar
    {{/}}
    Should show "FOO &amp; BAR": {{ var foo }}
 
String filters:

- ``upper``: convert to upper case
- ``lower``: convert to lower case
- ``ucfirst``: first string to upper case
- ``lcfirst``: first string to lower case
- ``ucwords``: first letter of every word to upper case
- ``trim``: trim all whitespace from both ends of string
- ``ltrim``: trim whitespace from start 
- ``rtrim``: trim whitespace from end
- ``rev``: reverse string
- ``striptags``: strip HTML tags from string (PHP function)
- ``base64``: convert to base64
- ``nl2spc``: convert one or more consecutive newlines into one space
- ``nl2br``: convert each newline to a ``<br />``


Guts Quickstart
---------------

Making your own language with Tempe's primitives is extremely easy:

.. code-block:: php

    <?php
    $handlers = [
        'foo'=>function($in, \Tempe\HandlerContext $context) { return 'foo'; },
        'bar'=>function($in, \Tempe\HandlerContext $context) { return 'bar'; },
    ];
    $lang = new \Tempe\Lang\Basic($handlers);
    $renderer = new \Tempe\Renderer($lang);

    echo $renderer->render('{{ foo }}{{ bar }}');

Handlers take two 


