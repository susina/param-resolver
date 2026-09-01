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

/**
 * Utility class to perform validations.
 */
final class Validator
{
    /**
     * Check if a value is only numeric or string.
     *
     * @param mixed $value The value to validate.
     * @throws ParamResolverException If the value is not numeric or string.
     * @return string The validated string value.
     */
    public static function validateString(mixed $value): string
    {
        if (!(is_numeric($value) || is_string($value))) {
            throw new ParamResolverException("A string value must be composed of strings and/or numbers.");
        }

        return (string) $value;
    }

    /**
     * Check if a generator is correctly closed.
     *
     * @param Generator $value The generator to validate.
     * @param int|string $key The key of the parameter being validated.
     * @throws ParamResolverException If the generator is not valid.
     * @return Generator The validated generator.
     */
    public static function validateGenerator(Generator $value, int|string $key): Generator
    {
        if (!$value->valid()) {
            throw new ParamResolverException("Parameter '$key' not found.");
        }

        return $value;
    }

    /**
     * Check if an environment variable exists.
     */
    public static function validateEnvParam(string $env): string
    {
        $envParam = getenv($env);

        if ($envParam === false) {
            throw new ParamResolverException("Environment variable '$env' is not defined.");
        }

        return $envParam;
    }
}
