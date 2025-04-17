<?php

namespace PHPNomad\Symfony\Component\Console;

use Symfony\Component\Console\Input\InputInterface as SymfonyInput;

class Input implements \PHPNomad\Console\Interfaces\Input
{
    protected SymfonyInput $input;
    protected array $overrides = [];
    protected mixed $output = null;

    public function __construct(SymfonyInput $input)
    {
        $this->input = $input;
    }

    public function getParam(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $this->overrides)) {
            return $this->overrides[$name];
        }

        if ($this->input->hasOption($name)) {
            return $this->input->getOption($name) ?? $default;
        }

        if ($this->input->hasArgument($name)) {
            return $this->input->getArgument($name) ?? $default;
        }

        return $default;
    }

    public function hasParam(string $name): bool
    {
        return $this->input->hasOption($name) || $this->input->hasArgument($name) || array_key_exists($name, $this->overrides);
    }

    public function setParam(string $name, mixed $value): static
    {
        $this->overrides[$name] = $value;
        return $this;
    }

    public function removeParam(string $name): static
    {
        unset($this->overrides[$name]);
        return $this;
    }

    public function getParams(): array
    {
        return array_merge(
            $this->input->getArguments(),
            $this->input->getOptions(),
            $this->overrides
        );
    }

    public function replaceParams(array $params): static
    {
        $this->overrides = $params;
        return $this;
    }
}
