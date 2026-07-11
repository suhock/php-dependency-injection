# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- ASP.NET-Core-style scoping support, including scoped builder methods
  `addScopedContainer()`, `addScopedNamespace()`, `addScopedInterface()`, and
  `addScopedAttribute()` for wiring nested, scope-bound containers.
- Deterministic disposal of services via `DisposableInterface`.
- `remove()` on `ContainerBuilderInterface`, plus a `key` parameter on
  `Container::remove()` for removing keyed services.
- `DependencyInjectionExceptionInterface`, an umbrella marker interface
  implemented by all exceptions thrown by the library.
- Keyed variants of all explicit builder methods.

### Changed

- Moved sources from `src/Suhock/DependencyInjection/*` to `src/*` (and the
  corresponding tests) to match the PSR-4 root.
- `DependencyInjectionException` now extends `RuntimeException` instead of
  `LogicException`.

### Removed

- `Container::getForContext()` is no longer part of the public surface.
- The container-lifecycle methods on `InstanceStore` are now marked `@internal`.

[Unreleased]: https://github.com/suhock/php-dependency-injection/compare/0.1.0...HEAD
