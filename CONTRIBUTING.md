# Contributing

Thanks for your interest in improving `suhock/dependency-injection`.

## Requirements

- PHP 8.1 or newer
- [Composer](https://getcomposer.org/)

## Getting started

```bash
composer install
```

## Running the checks

All checks must pass before a change can be merged:

```bash
composer test          # PHPUnit test suite
composer phpstan       # PHPStan, level 10
composer php-cs-fixer  # php-cs-fixer (coding standard)
```

CI runs the suite across PHP 8.1 through 8.4.

## Branch model

- Development happens on `develop`; `main` tracks released code.
- Fork the repository and create your branch from `develop`.
- Open pull requests against `develop`, not `main`.

## Expectations

- Add or update tests to cover your change; the suite must pass.
- PHPStan must pass at level 10.
- Follow the existing code style; run `composer php-cs-fixer` before pushing.
- Keep commits focused, with clear, imperative commit messages.
