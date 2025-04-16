<?php

namespace PHPNomad\Symfony\Component\Console\Strategies;

use PHPNomad\Console\Interfaces\OutputStrategy;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class ConsoleOutputStrategy implements OutputStrategy
{

    public function __construct(protected OutputInterface $output)
    {
        // Optional: Add custom styles for info/success/warning if desired
        $formatter = $this->output->getFormatter();

        if (!$formatter->hasStyle('info')) {
            $formatter->setStyle('info', new OutputFormatterStyle('blue'));
        }

        if (!$formatter->hasStyle('success')) {
            $formatter->setStyle('success', new OutputFormatterStyle('green'));
        }

        if (!$formatter->hasStyle('warning')) {
            $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));
        }

        if (!$formatter->hasStyle('error')) {
            $formatter->setStyle('error', new OutputFormatterStyle('red'));
        }
    }

    public function writeln(string $message): static
    {
        $this->output->writeln($message);
        return $this;
    }

    public function info(string $message): static
    {
        $this->output->writeln("<info>{$message}</info>");
        return $this;
    }

    public function success(string $message): static
    {
        $this->output->writeln("<success>{$message}</success>");
        return $this;
    }

    public function warning(string $message): static
    {
        $this->output->writeln("<warning>{$message}</warning>");
        return $this;
    }

    public function error(string $message): static
    {
        $this->output->writeln("<error>{$message}</error>");
        return $this;
    }

    public function newline(): static
    {
        $this->output->writeln('');
        return $this;
    }

    public function table(array $rows, array $headers = []): static
    {
        if (empty($rows)) {
            $this->output->writeln('<comment>No results found.</comment>');
            return $this;
        }

        $table = new Table($this->output);

        if (!empty($headers)) {
            $table->setHeaders($headers);
        } else {
            $firstRow = reset($rows);
            if (is_array($firstRow)) {
                $table->setHeaders(array_keys($firstRow));
            }
        }

        $table->setRows($rows);
        $table->render();

        return $this;
    }
}
