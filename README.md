sy-console
==========

Symfony Console component plus dependency injection, config handling, easily configured loading of commands, and other niceties.

Usage
-----

Like most Symfony projects these days, you start with [composer](https://getcomposer.org/), requiring `tjm/sy-console` as a dependency.  You'll create an application similar to [how you would with the symfony component alone](http://symfony.com/doc/current/components/console/introduction.html#creating-a-basic-command), but using the sy-console `Application` class and passing it configuration as an argument.

``` php
#!/usr/bin/env php
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use TJM\Component\Console\Application;

$app = new Application(__DIR__ . DIRECTORY_SEPARATOR . 'config.yml');
$app->run();
```

In the config file, you can set parameters, do imports, and configure services just like you would with a Symfony Standard app (although without a few of the niceties, like bundle path aliases).  There is a 'tjm_console' key for configuring the app itself.  This is where you set the name, version, and commands.

```
parameters:
 foo: bar
 paths.class: 'Foo\Component\Service\Paths'
 paths.settings:
  foo: '/foo/bar'
  bar: '/bar/foo'
 test.class: 'Foo\Component\Service\Test'

services:
 paths:
  class: %paths.class%
  arguments: ['@service_container', %paths.settings%]
 test:
  class: %test.class%
 App\Command\:
  autowire: true
  resource: '%paths.project%/src/Command'
  tags: ['console.command']

tjm_console:
 name: Test
 version: '1.0'
 rootNamespace: foo ## will alias all 'foo:' commands to the same names without the 'foo:'.  This is primarily to make commands easy to access but allow the same commands to be separated by namespace in another app
 commands:
  'Foo\Component\Command': '/Foo/src/Command' ## loads all commands in 'Foo\Component\Command' namespace from '/Foo/src/Command' folder
  'Foo\Component\Other\OtherCommand': '/Foo/src/Other/OtherCommand.php' ## loads single command class 'Foo\Component\Other\OtherCommand' from file '/Foo/src/Other/OtherCommand.php'
  0: 'Foo\Component\Other\Other2\Command' ## loads single command class 'Foo\Component\Other\Other2\Command' via autoloading
```

The commands key is an associative array, with the key being the namespace and the value being the folder or file path.  If the key is numeric, then the value will be the namespaced class name of the command, and it will use the autoloader to load the class.

You can also load classes as services, using the `console.command` tag, as seen in the `services` definition above.
