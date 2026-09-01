<?php

declare(strict_types=1);
/*
 * Copyright (c) Cristiano Cinotti 2024 - 2026.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *  http://www.apache.org/licenses/LICENSE-2.0
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace Susina\ParamResolver;

use Generator;
use Susina\ParamResolver\Exception\ParamResolverException;

final class ParamResolver
{
    /**
     * @var bool $resolved Flag to indicate if the parameters have been resolved.
     * It is used to prevent multiple resolutions of the same configuration array.
     */
    private bool $resolved = false;

    /**
     * @var mixed[] $config The array containing the values to manipulate while resolving parameters.
     * Usually, it is a configuration array. It's useful, in particular, for resolve() and get() method.
     */
    private array $config = [];

    /**
     * Static constructor.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Replaces parameter placeholders (%name%) by their values for all parameters.
     *
     * @param mixed[] $configuration The array to resolve

     * @throws ParamResolverException If a parameter is not found or if a circular reference is detected
     * @return mixed[] The resolved array
     */
    public function resolve(array $configuration): array
    {
        if ($this->resolved) {
            return [];
        }

        $this->config = $configuration;
        $parameters = [];
        foreach ($configuration as $key => $value) {
            $key = $this->resolveKey($key);
            $value = $this->resolveValue($value);
            $parameters[$key] = $this->unescapeValue($value);
        }

        $this->resolved = true;

        return $parameters;
    }

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param mixed $value The value to be resolved
     * @param array<string, true> $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @throws ParamResolverException If a parameter is not found or if a circular reference is detected.
     * @return mixed The resolved value
     */
    private function resolveValue(mixed $value, array $resolving = []): mixed
    {
        if (is_array($value)) {
            $args = [];
            foreach ($value as $k => $v) {
                $args[$this->resolveKey($k, $resolving)] = $this->resolveValue($v, $resolving);
            }

            return $args;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->resolveString($value, $resolving);
    }

    /**
     * Resolves parameters inside a string
     *
     * @param string $value The string to resolve
     * @param array<string, true> $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @throws ParamResolverException If a parameter is not found or if a circular reference is detected.
     * @return mixed The resolved value
     */
    private function resolveString(string $value, array $resolving = []): mixed
    {
        /*
         * %%: to be unescaped
         * %[^%\s]++%: a parameter
         *         ^ backtracking is turned off
         * when it matches the entire $value, it can resolve to any value.
         * otherwise, it is replaced with the resolved string or number.
         */

        /** @var string */
        $onlyKey = '';
        $replaced = preg_replace_callback('/%([^%\s]*+)%/', function (array $match) use ($resolving, $value, &$onlyKey): string {
            $key = $match[1];
            $env = $this->parseEnvironmentParams($key);

            $out = match (true) {
                $key === '' => '%%',
                $env !== null => $env,
                isset($resolving[$key]) => throw new ParamResolverException("Circular reference detected for parameter '$key'."),
                default => null,
            };

            if ($out !== null) {
                return $out;
            }

            if ($value === $match[0]) {
                $onlyKey = $key;

                return $match[0];
            }

            $resolved = Validator::validateString($this->get($key));

            $resolving[$key] = true;

            return $this->resolveString($resolved, $resolving);
        }, $value);

        if ($setKey = ($onlyKey !== '')) {
            $resolving[$onlyKey] = true;
        }

        return $setKey ? $this->resolveValue($this->get($onlyKey), $resolving) : $replaced;
    }

    /**
     * Return unescaped variable.
     *
     * @param mixed $value The variable to unescape
     *
     * @return mixed
     */
    private function unescapeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace('%%', '%', $value);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->unescapeValue($v);
            }

            return $result;
        }

        return $value;
    }

    /**
     * Return the value correspondent to a given key.
     *
     * @param int|string $propertyKey The key, in the configuration values array, to return the respective value
     *
     * @return mixed
     * @throws ParamResolverException when non-existent key in configuration array
     *
     */
    private function get(int|string $propertyKey): mixed
    {
        $value = $this->findValue($propertyKey, $this->config);

        return Validator::validateGenerator($value, $propertyKey)->current();
    }

    /**
     * Scan recursively an array to find a value of a given key.
     *
     * @param int|string $propertyKey The array key
     * @param mixed[] $config The array to scan
     *
     * @return \Generator The value or null if not found
     */
    private function findValue(int|string $propertyKey, array $config): Generator
    {
        foreach ($config as $key => $value) {
            if ($key === $propertyKey) {
                yield $value;
            }
            if (is_array($value)) {
                yield from $this->findValue($propertyKey, $value);
            }
        }
    }

    /**
     * Check if the parameter contains an environment variable and parse it
     *
     * @param string $value The value to parse
     *
     * @return string|null
     * @throws ParamResolverException if the environment variable is not set
     *
     */
    private function parseEnvironmentParams(string $value): ?string
    {
        // env.variable is an environment variable
        if (!str_starts_with($value, 'env.')) {
            return null;
        }

        return Validator::validateEnvParam(substr($value, 4));
    }

    /**
     * Resolve a value used as an array key.
     *
     * @param int|string $key
     * @param array<string, true> $resolving An array of keys that are being resolved (used internally to detect circular references)
     * @throws ParamResolverException
     * @return int|string
     */
    private function resolveKey(int|string $key, array $resolving = []): int|string
    {
        $resolved = $this->resolveValue($key, $resolving);

        if (!is_string($resolved) && !is_int($resolved)) {
            throw new ParamResolverException("Resolved key must be a string or an integer, got " . gettype($resolved));
        }

        return $resolved;
    }
}
