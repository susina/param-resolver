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

namespace Susina\ParamResolver\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Susina\ParamResolver\ParamResolver;
use Susina\ParamResolver\Exception\ParamResolverException;

class ParamResolverTest extends TestCase
{
    private ParamResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ParamResolver();
    }

    #[DataProvider('providerForResolveParams')]
    public function testResolveValues(array $conf, array $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($conf));
    }

    #[DataProvider('providerForResolveParams')]
    public function testResolveValuesStatic(array $conf, array $expected): void
    {
        $this->assertSame($expected, ParamResolver::create()->resolve($conf));
    }

    public function testResolveParametersWithEnvironmentVariables(): void
    {
        putenv('host=127.0.0.1');
        putenv('user=root');

        $config = [
            'HoMe' => 'myHome',
            'project' => 'myProject',
            'subhome' => '%HoMe%/subhome',
            'property1' => 1,
            'property2' => false,
            'directories' => [
                'project' => '%HoMe%/projects/%project%',
                'conf' => '%project%',
                'schema' => '%project%/schema',
                'template' => '%HoMe%/templates',
                'output%project%' => '/build',
            ],
            '%HoMe%' => 4,
            'host' => '%env.host%',
            'user' => '%env.user%',
        ];

        $expected = [
            'HoMe' => 'myHome',
            'project' => 'myProject',
            'subhome' => 'myHome/subhome',
            'property1' => 1,
            'property2' => false,
            'directories' => [
                'project' => 'myHome/projects/myProject',
                'conf' => 'myProject',
                'schema' => 'myProject/schema',
                'template' => 'myHome/templates',
                'outputmyProject' => '/build',
            ],
            'myHome' => 4,
            'host' => '127.0.0.1',
            'user' => 'root',
        ];

        $this->assertSame($expected, $this->resolver->resolve($config));

        //cleanup environment
        putenv('host');
        putenv('user');
    }

    public function testDoesNotCastToStringsTheReplacedValues(): void
    {
        $conf = $this->resolver->resolve(['foo' => true, 'expfoo' => '%foo%', 'bar' => null, 'expbar' => '%bar%']);

        $this->assertTrue($conf['expfoo']);
        $this->assertNull($conf['expbar']);
    }

    public function testInvalidPlaceholdersThrowsException(): void
    {
        $this->expectException(ParamResolverException::class);
        $this->expectExceptionMessageIs("Parameter 'baz' not found.");

        $this->resolver->resolve(['foo' => 'bar', '%baz%']);
    }

    public function testNotExistentPlaceholderThrowsException(): void
    {
        $this->expectException(ParamResolverException::class);
        $this->expectExceptionMessageIs("Parameter 'foobar' not found.");

        $this->resolver->resolve(['foo %foobar% bar']);
    }

    public function testSimpleCircularReferenceThrowsException(): void
    {
        $this->expectException(ParamResolverException::class);
        $this->expectExceptionMessageIs("Circular reference detected for parameter 'bar'.");

        $this->resolver->resolve(['foo' => '%bar%', 'bar' => '%foobar%', 'foobar' => '%foo%']);
    }

    public function testComplexCircularReferenceThrowsException(): void
    {
        $this->expectException(ParamResolverException::class);
        $this->expectExceptionMessageIs("Circular reference detected for parameter 'bar'.");

        $this->resolver->resolve(['foo' => 'a %bar%', 'bar' => 'a %foobar%', 'foobar' => 'a %foo%']);
    }

    public function testResolveWithEnvironmentVariables(): void
    {
        putenv('home=myHome');
        putenv('schema=mySchema');
        putenv('isBoolean=true');
        putenv('integer=1');

        $config = [
            'home' => '%env.home%',
            'property1' => '%env.integer%',
            'property2' => '%env.isBoolean%',
            'direcories' => [
                'projects' => '%home%/projects',
                'schema' => '%env.schema%',
                'template' => '%home%/templates',
                'output%env.home%' => '/build',
            ],
        ];

        $expected = [
            'home' => 'myHome',
            'property1' => '1',
            'property2' => 'true',
            'direcories' => [
                'projects' => 'myHome/projects',
                'schema' => 'mySchema',
                'template' => 'myHome/templates',
                'outputmyHome' => '/build',
            ],
        ];

        $this->assertEquals($expected, $this->resolver->resolve($config));

        //cleanup environment
        putenv('home');
        putenv('schema');
        putenv('isBoolean');
        putenv('integer');
    }

    public function testResolveParametersWithEmptyEnvironmentVariable(): void
    {
        putenv('home=');

        $config = [
            'home' => '%env.home%',
        ];

        $expected = [
            'home' => '',
        ];

        $this->assertEquals($expected, $this->resolver->resolve($config));

        //cleanup environment
        putenv('home');
    }

    public function testNotExistentEnvironmentVariablesThrowException(): void
    {
        $this->expectException(ParamResolverException::class);
        $this->expectExceptionMessageIs("Environment variable 'foo' is not defined.");

        $this->resolver->resolve(['home' => '%env.foo%']);
    }

    public function testNonStringParameterThrowsException(): void
    {
        $this->expectException(ParamResolverException::class);
        $this->expectExceptionMessageIs('A string value must be composed of strings and/or numbers.');

        $config = [
            'foo' => 'a %bar%',
            'bar' => [],
            'baz' => '%foo%',
        ];

        $this->resolver->resolve($config);
    }

    public function testResolveParamTwice(): void
    {
        $config = [
            'foo' => 'bar',
            'baz' => '%foo%',
        ];

        $this->assertSame(['foo' => 'bar', 'baz' => 'bar'], $this->resolver->resolve($config));
        $this->assertSame([], $this->resolver->resolve($config));
    }

    public static function providerForResolveParams(): array
    {
        return [
            [
                ['foo'],
                ['foo'],
            ],
            [
                ['foo' => 'bar', 'I\'m a %foo%'],
                ['foo' => 'bar', 'I\'m a bar'],
            ],
            [
                ['foo' => 'bar', '%foo%' => '%foo%'],
                ['foo' => 'bar', 'bar' => 'bar'],
            ],
            [
                ['foo' => 'bar', '%foo%' => ['%foo%' => ['%foo%' => '%foo%']]],
                ['foo' => 'bar', 'bar' => ['bar' => ['bar' => 'bar']]],
            ],
            [
                ['foo' => 'bar', 'I\'m a %%foo%%'],
                ['foo' => 'bar', 'I\'m a %foo%'],
            ],
            [
                ['foo' => 'bar', 'I\'m a %foo% %%foo %foo%'],
                ['foo' => 'bar', 'I\'m a bar %foo bar'],
            ],
            [
                ['foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']]],
                ['foo' => ['bar' => ['ding' => 'I\'m a bar %foo %bar']]],
            ],
            [
                ['foo' => 'bar', 'baz' => '%%%foo% %foo%%% %%foo%% %%%foo%%%'],
                ['foo' => 'bar', 'baz' => '%bar bar% %foo% %bar%'],
            ],
            [
                ['baz' => '%%s?%%s', '%baz%'],
                ['baz' => '%s?%s', '%s?%s'],
            ],
            [
                ['host' => 'foo.bar', 'port' => 1337, '%host%:%port%'],
                ['host' => 'foo.bar', 'port' => 1337, 'foo.bar:1337'],
            ],
            [
                ['foo' => 'bar', '%foo%'],
                ['foo' => 'bar', 'bar'],
            ],
            [
                ['foo' => 'bar', '% foo %'],
                ['foo' => 'bar', '% foo %'],
            ],
            [
                ['foo' => 'bar', '{% set my_template = "foo" %}'],
                ['foo' => 'bar', '{% set my_template = "foo" %}'],
            ],
            [
                ['foo' => 'bar', '50% is less than 100%'],
                ['foo' => 'bar', '50% is less than 100%'],
            ],
            [
                ['foo' => ['bar' => 'baz', '%bar%' => 'babar'], 'babaz' => '%foo%'],
                ['foo' => ['bar' => 'baz', 'baz' => 'babar'], 'babaz' => ['bar' => 'baz', 'baz' => 'babar']],
            ],
            [
                ['foo' => ['bar' => 'baz'], 'babaz' => '%foo%'],
                ['foo' => ['bar' => 'baz'], 'babaz' => ['bar' => 'baz']],
            ],
        ];
    }
}
