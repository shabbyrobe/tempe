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
Twig/Jinja2, see language_.

If you want to make your own simple, domain-specific templating language using Tempe's
primitives, see guts_.

.. contents::
    :backlinks: none
    :depth: 2


Why?
----

The usual reason: dissatisfaction with the existing options.

`Mustache <http://mustache.github.io/>`_ is great, but Mustache can stop you from doing
all the things in life you'd like to. Like those little bits of "logic" that aren't really
quite logic but you still need anyway. Context-specific escaping filters spring instantly
to mind.

`Twig <http://twig.sensiolabs.com/>`_ is also great, but it has its own issues. Complex
templates can be extremely cumbersome to work with due to how simple it is to allow
convoluted expression logic to bleed in to the template from your controller. This is easy
prey for ill-disciplined developers (I'm looking at you, everybody, and especially at you,
clock).

Both of these templating engines are fine choices, but I've spent too long bumping up
against the problems with both approaches. Tempe is an experiment in finding a
middle-ground. Remove the worst of the messy logic from Twig, keep the primitives as
simple as they can possibly be like Mustache.

Tempe's Guts are also much simpler than both Mustache and Twig - it comes with no features
by default, but the primitives it does provide make it possible to provide a
feature-complete replacement for either. Tempe comes bundled with an implementation of
these features you can use if you wish, though you do not have to - you are free to
implement your own handlers as you see fit.

Because of this design, Tempe can also be used to create your own templating DSL using its
Guts - you can strip it back to almost nothing and tailor it to your needs. This
makes it ideal for scenarios where you do not want to provide a complex templating system
but need a little bit more than ``strtr`` can give you (which is exactly the scenario that
led me to write it in the first place - ``strtr`` was too simple, Twig wasn't simple
enough, Mustache is too HTML-specific).


Primitives
----------

There are three primitives in Tempe - **value tags**, **block tags** and the **escape
sequence**.


Value and Block Tags
~~~~~~~~~~~~~~~~~~~~

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

Both block and value tags MAY contain a **chain** of **handlers**.

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
opening. The following does not require the escape sequence::

    {"json": {"yep": {{ get value | as js }} }}

But this example does::

    {"json": {;{{ get key | as js }}: "yep" }}


Language
--------

Play
~~~~

Tempe comes bundled with a configuration file for `boris
<https://github.com/d11wtq/boris>`_. Boris offers a PHP REPL. If you invoke ``boris`` from
the Tempe source directory, you will get a shell with Tempe set up and ready to go::

    ~/php/tempe$ boris
    Tempe Shell

    [1] boris> dumptpl("{{ get foo }}");
    0  1 P_ROOT     |  
    1  1   P_VALUE  |  get (foo)
     → NULL

    [2] boris> render("{{ get foo }}", ['foo'=>'bar']);
    Render:
    ---
    bar
    ---
    Parser time:  0.306ms
    Render time:  0.481ms
     → NULL


Handlers
~~~~~~~~

Get the variable ``foo`` and write to the output::

    {{ get foo }}

Get the variable ``foo``, escape as HTML then write to the output::

    {{ get foo | as html }}

Nested escape contexts can be handled in a single call to ``as``::

    <a href="url.php?arg={{ get foo | as html urlquery }}">foo</a>

.. warning::

    *Tempe* does not do any escaping by default. It is incumbent on the template author to
    be aware of the context in which they are emitting values **at all times**.
    
    Pádraic Brady's article `Automatic Output Escaping in PHP and the Real Future of
    Preventing Cross-Site Scripting (XSS)
    <http://blog.astrumfutura.com/2012/06/automatic-output-escaping-in-php-and-the-real-future-of-preventing-cross-site-scripting-xss/>`_
    is essential reading for anyone who believes that automatic output escaping isn't a
    bad idea.

Nested variable lookup::
    
    Given the hash {"foo": {"bar": "yep"}}
    This should print "yep": {{ get foo | get bar }}

Set a variable to the contents of a block::

    Should print nothing: {{# set foo }}Hello World{{/}}
    Should print "Hello World": {{ get foo }}

Set a variable from a different variable, overwriting if it already exists::

    {{# set foo }}hello{{/}}
    {{# set bar }}world{{/}}
    {{ get foo | set bar }}
    Should print hello: {{ get bar }}

Display a block if variable ``foo`` is truthy::

    {{# get foo | show }}Truthy!{{/}}

Display a block if variable ``foo`` is equal to the **value** ``hello``::

    {{# get foo | eq hello | show }}Hello!{{/}}

Display a block if variable ``foo`` is **not** equal to the **value** ``hello``::

    {{# get foo | eq hello | not | show }}Goodbye!{{/}}

``eq`` is limited to loose comparisons with **identifiers**. Comparisons can be done
between variables using ``eqvar``::

    Given the hash {"foo": "yep", "bar": "yep"}
    This block should render: 
    {{# get foo | eqvar bar | show }}foo is equal to bar!{{/}}

Complex expressions can be tested using a combination of ``set`` and ``eqvar``. This
allows the use of concatenation in comparisons::

    {{# set foo }}hel{{/}}
    {{# set bar }}lo{{/}}
    {{# set expr}}{{ get foo }}{{ get bar }}{{/}}
    {{# set test }}hello{{/}}
    {{# get expr | eqvar test | show }}This should show!{{/}}

Block iteration::

    With the following hash:
    {"foo": [ {"a": 1, "b": 2}, {"a": 3, "b": 4} ]}

    This template:
    {{# each foo }}
        Key:            {{ get _key_ }}
        Value:          {{ get _value_ | get a }}
        0-based index:  {{ get _idx_ }}
        1-based number: {{ get _num_ }}
        Is it first?:   {{#get _first_|show}}Yep!{{/}}{{#get _first_|not|show}}Nup!{{/}}

        `foo` is merged with the current scope:
            {{ get a }}, {{ get b }}
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

Push an array onto the current scope for a block::

    Given the hash:   {"foo": {"bar": "hello"}}
    The template:     {{# push foo }}{{ get bar }}{{/}}
    Should output:    hello

Build a nested array using ``push``::

    {{# a: push foo }}
    {{# b: push bar }}
    {{# set baz }}hello{{/}}
    {{/ b }}
    {{/ a }}
    Should print 'hello': {{ get foo | get bar | get baz }}

Handlers are chainable. This contrived example makes an entire block upper case, then html
escapes it, then sets it to another variable::

    {{# show | upper | as html | set foo }}
    foo & bar
    {{/}}
    Should show "FOO &amp; BAR": {{ get foo }}
 

String filters
~~~~~~~~~~~~~~

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


Guts
----

Making your own language with Tempe's primitives is extremely easy, you just need to write
your own handlers:

.. code-block:: php

    <?php
    $handlers = [
        'foo'=>function($handler, $in, \Tempe\HandlerContext $context) { return 'foo'; },
        'bar'=>function($handler, $in, \Tempe\HandlerContext $context) { return 'bar'; },
    ];
    $lang = new \Tempe\Lang\Basic($handlers);
    $renderer = new \Tempe\Renderer($lang);

    echo $renderer->render('{{ foo }}{{ bar }}');

.. note::
    
    The above handlers contain a fairly verbose way of representing the arguments. The
    rest of this guide will simply use ``($h, $in, $ctx)`` as a shorthand for ``($handler,
    $in, \Tempe\HandlerContext $context)``.


.. _handler-functions:

Handler Functions
~~~~~~~~~~~~~~~~~

Handler functions take three arguments:

``$handler``:
    An object containing the following properties:

    - ``name``: the handler name
    - ``args``: array of arguments to the handler
    - ``argc``: number of arguments

    Given the template ``{{ h 1 2 3 }}``, ``name`` will be set to ``h``, ``args`` will be
    set to ``[1, 2, 3]``, and ``argc`` will be set to 3.

``$in``:
    Contains the input from any previous handlers in the chain (or an empty string if the
    handler is the first). This is quite similar to how ``STDIN`` works in unix. Handlers
    can return anything at all, so be sure to include some sanity checks if you want
    decent error handling (not just crap like "Object of class BlahBlah could not be
    converted to string".

``$context``:
    An instance of ``Tempe\HandlerContext``, which has the following properties:
    
    ``renderer``
        The renderer which is calling the handler will be available here. You may call
        ``render`` against it without any ill effects.

    ``scope``
        array or ArrayAccess instance containing the current scope.

    ``chainPos``
        0-indexed position of this handler in the chain.

    ``break``
        Boolean, default ``false``. Set this to ``true`` if you want each subsequent
        handler in the chain to be ignored. You may still return a value from the handler
        even if you set break to ``true``.

    ``node``
        The node in the parse tree corresponding to this handler's tag. Use this, combined
        with ``renderer``, to recurse::
            
            $myHandler = function($handler, $in, $context) {
                return $context->renderer->renderTree($context->node, $context->scope);
            };

        You may replace, modify or omit ``$context->scope`` if you wish.


Nodes
~~~~~

The ``HandlerContext`` passed to a handler contains the node from the parse tree
corresponding to the handler's tag. A node object contains the following properties:

``type``
    Either ``\Tempe\Render::P_BLOCK`` or ``\Tempe\Renderer::P_VALUE``.

``line``
    The line in the template that this tag was opened on.

``id``
    If the tag contains an id (the part before the colon ``{{ myid: handler }}``, this
    will be available here, otherwise it will be ``null``.

``chain``
    The entire chain of handlers as an array of handler objects. Handler objects are
    described in handler-functions_.


If the node's type is ``\Tempe\Render::P_BLOCK``, it will also have the ``nodes``
property. It will contain an array of nodes representing the block's contents.


Recursion
~~~~~~~~~

``Tempe\Renderer`` does not recurse block tags automatically:

.. code-block:: php

    <?php
    $handlers = [
        'foo'=>function($h, $in, $ctx) { return 'foo'; },
        'bar'=>function($h, $in, $ctx) { throw new \Exception(); },
    ];
    $lang = new \Tempe\Lang\Basic($handlers);
    $renderer = new \Tempe\Renderer($lang);

    echo $renderer->render('{{# foo }}{{ bar }}{{/}}');

The above example prints ``foo``. The Exception is never triggered. If you want to write a
handler that returns the contents of the block, you can make use of the
``HandlerContext`` to render the node recursively:

.. code-block:: php

    <?php
    $handlers = [
        'foo'=>function($h, $in, $ctx) { 
            return $ctx->renderer->renderTree($ctx->node, $ctx->scope);
        },
        'bar'=>function($h, $in, $ctx) { return 'bar'; },
    ];
    $lang = new \Tempe\Lang\Basic($handlers);
    $renderer = new \Tempe\Renderer($lang);

    echo $renderer->render('{{# foo }}{{ bar }}{{/}}');

This time we get ``bar`` as our output.

If you do not pass ``$ctx->scope`` as the second argument to ``renderTree``, you will
lose access to the current scope inside the block. This may be exactly what you want, but
it probably isn't. You are free to modify the scope as you please before passing it to
``renderTree``. 

You should be aware of the difference between using an array and using an instance of
ArrayAccess as your scope if you are planning on making modifications in your block:

.. code-block:: php

    <?php
    $handlers = [
        'block'=>function($h, $in, $ctx) { 
            $scope = $ctx->scope;
            $scope['foo'] = 'inside';
            return $ctx->renderer->renderTree($ctx->node, $scope);
        },
        'get'=>function($h, $in, $ctx) { return $ctx->scope[$h->args[0]]; },
    ];
    $renderer = new \Tempe\Renderer(new \Tempe\Lang\Basic($handlers));

    $tpl = "{{# block }}{{ get foo }}{{/}} {{ get foo }}";

    $scope = ['foo'=>'outside'];
    assert("inside outside" == $renderer->render($tpl, $scope));

    $scope = new \ArrayObject(['foo'=>'outside']);
    assert("inside inside" == $renderer->render($tpl, $scope));


Rules
~~~~~

You can implement all of your validation as guard clauses directly in your handlers. You
should throw ``\Tempe\Exception\Check`` if the clause fails. If you pass the node's line
as the second argument, you will get better error messages.

.. code-block:: php

    <?php
    $lang = new \Tempe\Lang\Basic(['myHandler'=>function($h, $in, $ctx) {
        if ($h->argc != 1) {
            $msg = "myHandler expects 1 argument, found {$h->argc}";
            throw new \Tempe\Exception\Check($msg, $ctx->node->line);
        }
        if ($ctx->chainPos != 0) {
            $msg = "myHandler must be first in a chain, found at pos {$ctx->chainPos}";
            throw new \Tempe\Exception\Check($msg, $ctx->node->line);
        }
        return $h->args[0];
    }]);

This can get cumbersome if you have a lot of handlers, plus it will slow down rendering if
you are doing quite a lot of checking on every single handler invocation.

A better place to do the checking is during parsing. ``Tempe\Lang\Basic`` comes with a
simple way of specifying the most common rules, but you can pass arbitrary check functions
as well. These rules will be applied at parse time:

.. code-block:: php

    <?php
    $handlers = [
        'myHandler'=>function($h, $in, $ctx) {
            return $h->args[0];
        }
    ];
    $rules = [
        'myHandler'=>['argc'=>1, 'first'=>true],
    ];
    $lang = new \Tempe\Lang\Basic($handlers, $rules);

    // if you are creating the parser by hand, you must pass the language
    $parser = new \Tempe\Parser($lang);
    $renderer = new \Tempe\Renderer($lang, $parser);

    // if you are allowing the renderer to create the default parser for you, 
    // the language will also be passed.
    $renderer = new \Tempe\Renderer($lang);

    // throws "Handler 'myHandler' expected 1 arg(s), found 2 at line 1"
    $renderer->render('{{ myHandler a b }}');

    // throws "Handler 'myHandler' expected to be first, but found at pos 2 at line 1
    $renderer->render('{{ myHandler a | myHandler a b }}');


You can also instruct the renderer to check while rendering if you like. This can be
useful if you want to cache the parse tree and ensure that it is still valid during
rendering, but it will slow the render down so it is off by default.

.. code-block:: php

    <?php
    $renderer = new \Tempe\Renderer($lang, $parser, !!'check');

    // use the default lang and parser
    $renderer = new \Tempe\Renderer(null, null, !!'check');

    // set it as a property instead
    $renderer = new \Tempe\Renderer();
    $renderer->check = true;


Available rules
^^^^^^^^^^^^^^^

``argc`` - int
    Handler argument count must be exactly equal to this

``argMin`` - int
    Handler argument count must not be less than this. Ignored if ``argc`` set.
    
``argMax`` - int
    Handler argument count must not be more than this. Ignored if ``argc`` set.

``allowValue`` - bool, default: true
    Set this to false to prevent the handler from being used on **value** tags

``allowBlock`` - bool, default: true
    Set this to false to prevent the handler from being used on **block** tags

``chainable`` - bool, default: true
    Set this to false if you want this to be the only handler in a chain. If ``chainable``
    is false for handler ``lonesome``::
    
        Valid: 
            {{ lonesome }}
            {{# lonesome }}{{/}}

        Invalid:
            {{ foo | lonesome | bar }}
            {{ lonesome | bar }}
            {{ bar | lonesome }}
    
``last`` - bool, default: null
    If ``true``, no handlers can come after this one in a chain. Valid: ``{{ foo |
    mustbelast }}``. Invalid: ``{{ foo | mustbelast | bar }}``.

    If ``false``, this handler must not be last in a chain. Valid: ``{{ foo |
    mustnotbelast | bar }}``. Invalid: ``{{ foo | bar | mustnotbelast }}``.

``first`` - bool, default: null
    If ``true``, this handler **must** be the first handler in the chain. Valid: ``{{
    mustbefirst | foo }}``. Invalid: ``{{ foo | mustbefirst }}``

    If ``false``, this handler **must not** be first in the chain. Valid: ``{{ foo |
    mustnotbefirst }}``. Invalid: ``{{ mustnotbefirst }}``.

``check`` - callable
    Pass any function you like to this. It will receive the following arguments::

        function check($handler, $node, $chainPos)

    You MUST return ``true`` for the handler to pass. If you return something falsey or
    nothing at all, you receive a generic exception which may not be particularly helpful. 

    For the sake of your users, you should throw ``Tempe\Exception\Check`` with a
    descriptive message.

    .. code-block:: php
        
        <?php
        $handlers = [
            'foo'=>function($handler) {
                return $handler->args[0];
            },
        ];
        $rules = [
            'foo'=>['check'=>function($handler, $node, $chainPos) {
                if ($handler->args[0] != 'foo') {
                    $msg = "For some reason, you can only pass 'foo' as the first argument";
                    throw new \Tempe\Exception\Check($msg, $node->line);
                }
                return true;
            }],
        ];
        $lang = new \Tempe\Lang\Basic($handlers, $rules);


Parsing
~~~~~~~

``Tempe\Parser`` will take a template and turn it into a parse tree.

Perhaps the best way of demonstrating how the parser works is to show you the output of
``Tempe\Helper::dumpNode($node)``.

.. code-block:: php

    <?php
    $tpl = "
    Here's a value tag. The handler is 'hello':
    {{ hello world }}

    Here's a chained value tag:
    {{ foo bar | baz qux | ding dang dong }}

    Ooh, escape sequence:
    {;{ foo bar }}

    Here's a named block tag with some stuff inside:
    {{# mystuff: group }}
        {{ pants }}
        {{# morestuff }}{{ pants }}{{/}}
    {{/ mystuff }}
    ";
    $parser = new \Tempe\Parser();
    \Tempe\Helper::dumpNode($parser->parse($tpl));

The output (columns are depth, line, type or id, and info)::
 
    0   1 P_ROOT          |  
    1   1   P_STRING      |  "Here's a value tag. ..."
    1   2   P_VALUE       |  hello (world)
    1   2   P_STRING      |  "\n\nHere's a chained v..."
    1   5   P_VALUE       |  foo (bar) -> baz (qux) -> ding (dang dong)
    1   5   P_STRING      |  "\n\nOoh, escape sequen..."
    1   8   P_ESC         |  
    1   8   P_STRING      |  "{ foo bar }}\n\nHere's..."
    1  11   mystuff       |  group ()
    2  11     P_STRING    |  "\n    "
    2  12     P_VALUE     |  pants ()
    2  12     P_STRING    |  "\n    "
    2  13     P_BLOCK     |  morestuff ()
    3  13       P_VALUE   |  pants ()
    2  13     P_STRING    |  "\n"

.. note::

   If you run ``\Tempe\Helper::dumpNode()`` from the CLI, you will get fancy formatting in
   the output. It's actually quite nice, I initially regretted wasting the time writing it
   but it has proven invaluable.


Completely Custom Language
~~~~~~~~~~~~~~~~~~~~~~~~~~

You don't like, want or need what ``Tempe\Lang\Basic`` offers? No problem! Just implement
``Tempe\Lang`` yourself:

.. code-block:: php

    <?php
    class MyLang implements \Tempe\Lang
    {
        function check($handler, $node, $chainPos)
        {
            return true;
        }

        function handle($handler, $in, \Tempe\HandlerContext $context)
        {
            switch ($handler->name) {
            case 'foo': return "foo "; break;
            case 'bar': return "bar "; break;
            default: return $handler->name."(".implode(", ", $handler->args).") ";
            }
        }

        function handleEmpty(\Tempe\HandlerContext $context)
        {
            return "<empty>";
        }
    }
    $lang = new MyLang();
    $renderer = new \Tempe\Renderer($lang);
    echo $renderer->render("{{ foo }}{{ bar }}{{ baz qux }}{{}}");

Output::

    foo bar baz(qux) <empty>

