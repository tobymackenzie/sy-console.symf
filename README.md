sy-console
==========

Symfony Console component plus dependency injection, config handling, easily configured loading of commands, and other niceties.

Usage
-----

Like most Symfony projects these days, you start with [composer](https://getcomposer.org/), putting sy-console as a dependency.  You'll create an application similar to [how you would with the symfony component alone](http://symfony.com/doc/current/components/console/introduction.html#creating-a-basic-command), but using the sy-console 'Application' class and passing it configuration as an argument.

``` php
#!/usr/bin/env php
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use TJMComponentConsoleApplication;

$app = new Application(__DIR__ . DIRECTORY_SEPARATOR . 'config.yml');
$app->run();
```

In the config file, you can set parameters, do imports, and configure services just like you would with a Symfony Standard app (although without a few of the niceties, like bundle path aliases).  There is a 'tjm_console' key for configuring the app itself.  This is where you set the name, version, and commands.

```
parameters:
 foo: bar
 paths.class: 'FooComponentServicePaths'
 paths.settings:
  foo: '/foo/bar'
  bar: '/bar/foo'
 test.class: 'FooComponentServiceTest'

services:
 paths:
  class: %paths.class%
  arguments: ['@service_container', %paths.settings%]
 test:
  class: %test.class%

tjm_console:
 name: Test
 version: '1.0'
 rootNamespace: foo ## will alias all 'foo:' commands to the same names without the 'foo:'.  This is primarily to make commands easy to access but allow the same commands to be separated by namespace in another app
 commands:
  'FooComponentCommand': '/Foo/src/Command' ## loads all commands in 'FooComponentCommand' namespace from '/Foo/src/Command' folder
  'FooComponentOtherOtherCommand': '/Foo/src/Other/OtherCommand.php' ## loads single command class 'FooComponentOtherOtherCommand' from file '/Foo/src/Other/OtherCommand.php'
  0: 'FooComponentOtherOther2Command' ## loads single command class 'FooComponentOtherOther2Command' via autoloading
```

The commands key is an associative array, with the key being the namespace and the value being the folder or file path.  If the key is numeric, then the value will be the namespaced class name of the command, and it will use the autoloader to load the class.  This may be confusing, so I may swap them, but that is how it is currently.

Future
------

This project is new and doesn't have much time into it.  Some of the things I may try to do as I work with it:

- get interfaces to a stable place
- make sure I am using all that makes sense from the components I'm using, and that I'm using all components that make sense
- get unit testing in place so I can make sure changes don't break things
- re-add support for passing a configuration array to Application (this was removed when I went to using the dependency injection for configuration, but I think I can make it work)
- make sure 'bundles' of commands and services for this play nice when used in Symfony Standard Edition and vice versa
