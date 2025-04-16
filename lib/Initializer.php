<?php

namespace PHPNomad\Symfony\Component\Console;


use PHPNomad\Console\Interfaces\ConsoleStrategy as CoreConsoleStrategy;
use PHPNomad\Console\Interfaces\OutputStrategy;
use PHPNomad\Di\Interfaces\CanSetContainer;
use PHPNomad\Di\Traits\HasSettableContainer;
use PHPNomad\Loader\Interfaces\HasClassDefinitions;
use PHPNomad\Loader\Interfaces\Loadable;
use PHPNomad\Symfony\Component\Console\Strategies\ConsoleOutputStrategy;
use PHPNomad\Symfony\Component\Console\Strategies\ConsoleStrategy;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Initializer implements HasClassDefinitions, Loadable, CanSetContainer
{
    use HasSettableContainer;
    public function getClassDefinitions(): array
    {
        return [
            ConsoleOutputStrategy::class => OutputStrategy::class,
            ConsoleStrategy::class       => CoreConsoleStrategy::class
        ];
    }

    public function load(): void
    {
        $this->container->bindSingletonFromFactory(
            OutputInterface::class,
            fn() => new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, null, new OutputFormatter())
        );
    }
}