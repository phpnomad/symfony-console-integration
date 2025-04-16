<?php

namespace PHPNomad\Symfony\Component\Console\Strategies;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface as SymfonyInput;
use Symfony\Component\Console\Output\OutputInterface as SymfonyOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use PHPNomad\Console\Interfaces\ConsoleStrategy as ConsoleStrategyInterface;
use PHPNomad\Console\Interfaces\Command as NomadCommand;
use PHPNomad\Symfony\Component\Console\Input as NomadInput;
use PHPNomad\Console\Interfaces\HasMiddleware;
use PHPNomad\Console\Interfaces\HasInterceptors;
use PHPNomad\Console\Exceptions\ConsoleException;
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Utils\Helpers\Arr;
use PHPNomad\Utils\Helpers\Str;

class ConsoleStrategy implements ConsoleStrategyInterface
{
    protected Application $app;
    protected LoggerStrategy $logger;

    public function __construct(LoggerStrategy $logger, ?Application $app = null)
    {
        $this->app = $app ?: new Application();
        $this->logger = $logger;
    }

    public function registerCommand(callable $commandGetter): void
    {
        $nomadCommand = $commandGetter();
        $parsed = $this->parseSignature($nomadCommand->getSignature());

        $symfonyCommand = new class($nomadCommand, $parsed, $this->logger) extends SymfonyCommand {
            protected NomadCommand $command;
            protected array $parsed;
            protected LoggerStrategy $logger;

            public function __construct(NomadCommand $command, array $parsed, LoggerStrategy $logger)
            {
                $this->command = $command;
                $this->parsed = $parsed;
                $this->logger = $logger;

                parent::__construct($parsed['name']);
                $this->setDescription($command->getDescription());

                foreach ($parsed['definitions'] as $def) {
                    if ($def['isOption']) {
                        $this->addOption(
                            $def['name'],
                            null,
                            $def['required'] ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL,
                            $def['description'],
                            $def['default']
                        );
                    } else {
                        $this->addArgument(
                            $def['name'],
                            $def['required'] ? InputArgument::REQUIRED : InputArgument::OPTIONAL,
                            $def['description'] ?? '',
                            $def['default']
                        );
                    }
                }
            }

            protected function execute(SymfonyInput $input, SymfonyOutput $output): int
            {
                $nomadInput = new NomadInput($input);
                $nomadOutput = new ConsoleOutputStrategy($output);

                try {
                    if ($this->command instanceof HasMiddleware) {
                        foreach ($this->command->getMiddleware($nomadInput) as $middleware) {
                            $middleware->process($nomadInput);
                        }
                    }

                    $exitCode = $this->command->handle($nomadInput->setOutput($nomadOutput));

                    if ($this->command instanceof HasInterceptors) {
                        foreach ($this->command->getInterceptors($nomadInput) as $interceptor) {
                            $interceptor->process($nomadInput, $exitCode);
                        }
                    }

                    return $exitCode;
                } catch (ConsoleException $e) {
                    $this->logger->logException($e);
                    $nomadOutput->error($e->getMessage());
                    return 1;
                }
            }
        };

        $this->app->add($symfonyCommand);
    }

    public function run(): void
    {
        $this->app->run();
    }

    /**
     * Parses a PHPNomad signature string.
     */
    protected function parseSignature(string $signature): array
    {
        preg_match_all('/{([^}]+)}/', $signature, $matches);
        $rawParams = $matches[1];
        $commandName = trim(preg_replace('/{[^}]+}/', '', $signature));

        $definitions = Arr::process($rawParams)->map(function (string $raw) {
            $isOption = Str::startsWith($raw, '--');
            $description = '';
            $default = null;
            $required = true;

            if (Str::contains($raw, ':')) {
                [$raw, $description] = explode(':', $raw, 2);
            }

            if ($isOption) {
                $name = Str::trimLeading($raw, '-');

                if (Str::contains($name, '=')) {
                    [$name, $default] = explode('=', $name, 2);
                    $required = $default === '';
                }

                return [
                    'name' => $name,
                    'isOption' => true,
                    'required' => $required,
                    'default' => $default,
                    'description' => $description,
                ];
            }

            $name = Str::trimTrailing($raw, '?');
            $optional = Str::endsWith($raw, '?');
            $required = !$optional;

            return [
                'name' => $name,
                'isOption' => false,
                'required' => $required,
                'default' => null,
                'description' => $description,
            ];
        })->toArray();

        return [
            'name' => $commandName,
            'definitions' => $definitions,
        ];
    }
}
