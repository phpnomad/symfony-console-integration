# phpnomad/symfony-console-integration

[![Latest Version](https://img.shields.io/packagist/v/phpnomad/symfony-console-integration.svg)](https://packagist.org/packages/phpnomad/symfony-console-integration)
[![Total Downloads](https://img.shields.io/packagist/dt/phpnomad/symfony-console-integration.svg)](https://packagist.org/packages/phpnomad/symfony-console-integration)
[![PHP Version](https://img.shields.io/packagist/php-v/phpnomad/symfony-console-integration.svg)](https://packagist.org/packages/phpnomad/symfony-console-integration)
[![License](https://img.shields.io/packagist/l/phpnomad/symfony-console-integration.svg)](https://packagist.org/packages/phpnomad/symfony-console-integration)

Integrates [Symfony Console](https://symfony.com/doc/current/components/console.html) with PHPNomad's console abstraction. Your commands implement `phpnomad/console`'s `Command` interface and declare their arguments through PHPNomad's signature syntax. This package parses those signatures, registers the commands with a Symfony `Application`, and runs `handle()` through any declared middleware and interceptors. Your command classes never reference Symfony directly.

## Installation

```bash
composer require phpnomad/symfony-console-integration
```

## What This Provides

- `ConsoleStrategy` parses a PHPNomad signature into Symfony `InputArgument` and `InputOption` definitions, wraps the command in an anonymous Symfony `Command` subclass, and runs `handle()` through any `HasMiddleware` and `HasInterceptors` hooks. `ConsoleException` failures are logged via `LoggerStrategy` and surfaced through `OutputStrategy::error()` with exit code 1.
- `ConsoleOutputStrategy` maps `writeln`, `info`, `success`, `warning`, `error`, `newline`, and `table` onto a Symfony `OutputInterface` with blue, green, yellow, and red formatter styles.
- `Input` wraps a Symfony `InputInterface` behind PHPNomad's `Input` contract, with an override map so middleware can mutate values without touching the Symfony request.
- `Initializer` is a `phpnomad/loader` initializer that binds the strategies to their PHPNomad interfaces and provides a singleton Symfony `OutputInterface` (a `ConsoleOutput` with the default formatter).

## Requirements

- `phpnomad/console`, the contract this package implements
- `symfony/console` ^7.2, the library this package bridges
- `phpnomad/logger` for exception logging inside the command wrapper
- `phpnomad/utils` and `phpnomad/loader` for bootstrapping

## Usage

Bind a Symfony `Application` into your container, pass this package's `Initializer` to your `Bootstrapper` alongside any initializer that declares commands via `HasCommands`, then call `run()` on the resolved `ConsoleStrategy`:

```php
<?php

use MyApp\Commands\CommandsInitializer;
use PHPNomad\Console\Interfaces\ConsoleStrategy;
use PHPNomad\Loader\Bootstrapper;
use PHPNomad\Symfony\Component\Console\Initializer as SymfonyConsoleInitializer;
use Symfony\Component\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../bootstrap/container.php';

$container->bindSingletonFromFactory(
    Application::class,
    fn() => new Application('MyApp CLI')
);

$bootstrapper = new Bootstrapper(
    $container,
    new SymfonyConsoleInitializer(),
    new CommandsInitializer()
);

$bootstrapper->load();

$container->get(ConsoleStrategy::class)->run();
```

`CommandsInitializer` is any class implementing `HasCommands`. Its `getCommands()` returns an array of command class names, and the loader registers each one automatically. Each command class implements `Command`, with `getSignature()` returning a string like `widget:create {name:The widget name} {--force}` and `handle(Input $input)` running the work.

## Documentation

PHPNomad documentation lives at [phpnomad.com](https://phpnomad.com). For the underlying library, see the [Symfony Console documentation](https://symfony.com/doc/current/components/console.html).

## License

Licensed under the [MIT License](LICENSE.txt).
