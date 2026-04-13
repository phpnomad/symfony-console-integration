<?php

namespace PHPNomad\Symfony\Component\Console\Strategies;

use PHPNomad\Console\Interfaces\Input;
use PHPNomad\Console\Interfaces\OutputStrategy;
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
    public function __construct(
        protected LoggerStrategy $logger,
        protected Application $app,
        protected OutputStrategy $outputStrategy
    )
    {
    }

    public function registerCommand(callable $commandGetter): void
    {
        // Parse signature eagerly (required for Symfony registration),
        // but defer command instantiation to execute() time.
        $nomadCommand = $commandGetter();
        $parsed = $this->parseSignature($nomadCommand->getSignature());
        $description = $nomadCommand->getDescription();

        $symfonyCommand = new class($this->outputStrategy, $commandGetter, $parsed, $description, $this->logger) extends SymfonyCommand {
            /** @var callable */
            protected $commandGetter;
            protected array $parsed;
            protected LoggerStrategy $logger;
            protected OutputStrategy $outputStrategy;

            public function __construct(OutputStrategy $outputStrategy, callable $commandGetter, array $parsed, string $description, LoggerStrategy $logger)
            {
                $this->outputStrategy = $outputStrategy;
                $this->commandGetter = $commandGetter;
                $this->parsed = $parsed;
                $this->logger = $logger;

                parent::__construct($parsed['name']);
                $this->setDescription($description);

                foreach ($parsed['definitions'] as $def) {
                    if ($def['isOption']) {
                        if ($def['isFlag'] ?? false) {
                            $this->addOption(
                                $def['name'],
                                null,
                                InputOption::VALUE_NONE,
                                $def['description']
                            );
                        } else {
                            $this->addOption(
                                $def['name'],
                                null,
                                $def['required'] ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL,
                                $def['description'],
                                $def['default']
                            );
                        }
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
                $command = ($this->commandGetter)();
                $nomadInput = new NomadInput($input);

                try {
                    if ($command instanceof HasMiddleware) {
                        foreach ($command->getMiddleware($nomadInput) as $middleware) {
                            $middleware->process($nomadInput);
                        }
                    }

                    $exitCode = $command->handle($nomadInput);

                    if ($command instanceof HasInterceptors) {
                        foreach ($command->getInterceptors($nomadInput) as $interceptor) {
                            $interceptor->process($nomadInput, $exitCode);
                        }
                    }

                    return $exitCode;
                } catch (ConsoleException $e) {
                    $this->logger->logException($e);
                    $this->outputStrategy->error($e->getMessage());
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
                $raw = ltrim($raw, '-');

                if (Str::contains($raw, '=')) {
                    [$name, $default] = explode('=', $raw, 2);
                    $required = false;
                } else {
                    $name = $raw;
                    $default = null;
                    $required = false;

                    return [
                        'name' => $name,
                        'isOption' => true,
                        'isFlag' => true,
                        'required' => false,
                        'default' => null,
                        'description' => $description,
                    ];
                }

                return [
                    'name' => $name,
                    'isOption' => true,
                    'isFlag' => false,
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
                'isFlag' => false,
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
