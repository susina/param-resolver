# Parameter Resolver

![Test Suite](https://github.com/susina/param-resolver/actions/workflows/test.yml/badge.svg)
[![Maintainability](https://qlty.sh/badges/15b08566-7e7a-46ca-b120-2e24af96de83/maintainability.svg)](https://qlty.sh/gh/susina/projects/param-resolver)
[![Code Coverage](https://qlty.sh/badges/15b08566-7e7a-46ca-b120-2e24af96de83/test_coverage.svg)](https://qlty.sh/gh/susina/projects/param-resolver)

ParamResolver is a small class to resolve parameters in configuration arrays.
It's heavily inspired on [Symfony ParameterBag](src/Symfony/Component/DependencyInjection/ParameterBag/ParameterBag.php) class.

## Installation

Install the library via composer:

`composer require susina/param-resolver`


## Usage

In a configuration array, it can be useful to define some parameters.

A parameter is a previously defined property, put between % special character. When ParamResolver found a parameter, it simply replaces its placeholder with the previously defined value. In the following example, suppose you have a json configuration file:

```json
// configuration.json
{
    "library": {
        "project": "AwesomeProject"
    },
    "paths": {
        "projectDir": "/home/%project%"
    }
}
```

First of all you have to convert it into an array, then you can resolve the parameters:

```php
<?php declare(strict_types=1);

    use Susina\ParamResolver\ParamResolver;

    //load and convert into an array
    $array = json_decode('configuration.json');

    //resolve the parameters
    $resolved = ParamResolver::create()->resolve($array);

    //print the resolved array or else
    echo json_encode($resolved);
```

Now the json content is the following:

```json
{
    "library": {
        "project": "AwesomeProject"
    },
    "paths": {
        "projectDir": "/home/AwesomeProject"
    }
}
```

You can escape the special character % by doubling it:

```json
// configuration.json
{
    "discounts": {
        "jeans": "20%%"
    }
}
```

jeans property now contains the string '20%'.

> [!Note]
> Both keys and values of your array can contain parameters.

### Special parameters: environment variables

The string `env` is used to specify an environment variable.

Many hosts give services or credentials via environment variables and you can use them in your configuration file via `env.variable` syntax. In example, let’s suppose to have the following environment variables:

```php
<?php

$_ENV['host']   = '192.168.0.54'; //Database host name
$_ENV['dbName'] = 'myDB'; //Database name
```

In your (yaml) configuration file you can write:

```yaml
database:
  connections:
      default:
          adapter: mysql
          dsn: mysql:host=%env.host%;dbname=%env.dbName%
```

and, after processing, it becomes:

```yaml
database:
  connections:
      default:
          adapter: mysql
          dsn: mysql:host=192.168.0.54;dbname=myDB
```

## Issues

If you find a bug or any other issue,  please report it on [Github](https://github.com/susina/param-resolver/issues).

## Contributing

Please, see [CONTRIBUTING.md](CONTRIBUTING.md)

## Licensing

This library is released under [Apache-2.0](LICENSE) license.
