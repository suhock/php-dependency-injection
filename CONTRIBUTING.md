# Contributing

## Requirements

- PHP 8.4 or newer
- [Composer](https://getcomposer.org/)

## Getting started

```bash
composer install
```

## Running the checks

All checks must pass before a change can be merged:

```bash
composer test
composer phpstan
composer php-cs-fixer
```

CI runs the suite across all versions of PHP supported by this library.

## Branch model

- Development happens on `develop`; `main` tracks released code.
- Fork the repository and create your branch from `develop`.
- Open pull requests against `develop`, not `main`.

## Expectations

- Add or update tests to cover your change; the suite must pass.
- PHPStan must pass with zero issues.
- Follow the existing code style; run `composer php-cs-fixer` before pushing.
- Keep commits focused, with clear, imperative commit messages.
