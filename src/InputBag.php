<?php
/*
 *  Author: Aaron Sollman
 *  Email:  unclepong@gmail.com
 *  Date:   12/02/25
 *  Time:   23:34
*/


namespace Foamycastle\HTTP;

/**
 * Helper class for managing input data
 */
class InputBag
{
    protected array $parameters = [];

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function all(): array
    {
        return $this->parameters;
    }

    public function add(array $parameters): void
    {
        $this->parameters = array_replace($this->parameters, $parameters);
    }

    public function replace(array $parameters): void
    {
        $this->parameters = $parameters;
    }
}