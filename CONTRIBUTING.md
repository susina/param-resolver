# Contributing

First of all, many thanks to spend your time on this library!

## Workflow

### Pre-requisites

1. Have [PHP 8.5](https://php.net) installed.
2. Have [Composer](https://getcomposer.org) installed to manage dependencies and autoloading.
3. Have [Phive](https://phive.io) to install and manage our development tools (PhpUnit, PhpStan etc.) avoiding dependencies conflicts.

1. Fork [susina/param-resolver](https://github.com/susina/param-resolver) repository.
2. Run `composer install` to install dependencies and create the correct autoloading map.
3. Run `phive install` to safely install our development tools.
4. Apply your patches.
5. Run the test suite by `composer test` command and fix all red tests.
6. Run static analysis tool by `composer analytics` command and fix all errors.
7. Fix the coding standard by running `composer cs:fix`.

> [!TIP]
> We provide a __check__ command which runs the test suite, analytics tool and coding standard fix.
> So, before submitting a pull request you can simply run `composer check`.

## Running the Test Suite

While developing, the test part is very important: if you apply a patch to the existing code, the test suite must run without errors or failures and if you add a new functionality, no one will consider it without tests.

Our test tool is [PhpUnit](https://phpunit.de/) and we provide a script to launch it:

```bash
composer test
```

## Code Coverage

We provides two commands to generate the code coverage report in _html_ or _xml_ format:

-  `composer coverage:html` command generates a code coverage report in _html_ format, into the directory `coverage/`
-  `composer coverage:clover` generates the report in _xml_ format, into `clover.xml` file.


## Static Analysis Tool

To prevent as many bugs as possible, we use a static analysis tool called [PHPStan](https://phpstan.org/).
To launch it, run the following command:

```bash
composer analytics
```

After its analysis, PHPStan outputs errors and issues with its suggestions on how to fix them.


## Coding Standard

We ship our script to easily fix coding standard errors, via [php-cs-fixer](https://cs.symfony.com/) tool.
To fix coding standard errors just run:

```bash
composer cs:fix
```

and to show the errors without fixing them, run:

```bash
composer cs:check
```

All the repositories inside Susina Project follow [PER 3.x](https://www.php-fig.org/per/coding-style/) coding style.