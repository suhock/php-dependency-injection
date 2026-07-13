# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `ContainerBuilder`, a final class carrying the full mutable configuration
  surface (`add*`, `addKeyed*`, `remove()`, `configure()`) previously mixed
  directly into `Container`.
- `ContainerBuilder::build(): Container`, which compiles the whole dependency
  graph into per-service resolution plans, validates it, and produces an
  immutable `Container`. Every added service must be resolvable at build:
  configuration defects — missing dependencies, invalid `#[Inject]` members,
  required-edge cycles, captive dependencies, and more — are aggregated into
  one `ContainerValidationException`.
- `ContainerBuilder::createDefault(?CacheInterface $cache = null)`, replacing
  `Container::createDefault()`.
- Automatic binding of `ContainerInterface` (to the current resolution root,
  so a service resolved from a scope receives that scope) and
  `ScopeFactoryInterface` (to the root `Container`).
- Compiled-graph reuse: with a shared cache, rebuilding an unchanged
  configuration loads the stored plan set and skips compilation and
  validation entirely.
- Support for PHP 8.5 (added to the CI test matrix).
- ASP.NET-Core-style scoping support, including scoped variants of the
  builder methods.
- Deterministic disposal of services via `DisposableInterface`.
- `remove()` on `ContainerBuilderInterface`, including an optional `key`
  parameter for removing keyed services.
- `DependencyInjectionExceptionInterface`, an umbrella marker interface
  implemented by all exceptions thrown by the library.
- Keyed variants of all explicit builder methods.

### Changed

- `Container` is now the immutable product of `ContainerBuilder::build()`: it
  exposes only `get()`, `has()`, `createScope()`, and `dispose()`, and
  resolves services by executing the plans compiled at build time. The
  pattern of adding a service that is deliberately never resolvable is no
  longer supported.
- `Injector::createDefault()` instantiates through the universal reflection
  strategy; the fast-path analysis and its cache are gone.
- Moved sources from `src/Suhock/DependencyInjection/*` to `src/*` (and the
  corresponding tests) to match the PSR-4 root.
- `DependencyInjectionException` now extends `RuntimeException` instead of
  `LogicException`.

### Removed

- `Container::createDefault()` and every builder method on `Container` — use
  `ContainerBuilder`.
- `FastPathInstantiationStrategy`; descriptor-backed resolution executes
  compiled plans instead, and the standalone `Injector` uses reflection.
- Nested containers: `addContainer()`, the
  `add{Singleton,Transient}{Namespace,Interface,Attribute}()` builder methods,
  and the `NamespaceContainer`, `InterfaceContainer`, `AttributeContainer`,
  and `AbstractFactoryContainer` classes. Services are now always added
  explicitly; build-time enumeration of conventions may return in a
  validatable form after 1.0.
- `Container::getForContext()` is no longer part of the public surface.
- The container-lifecycle methods on `InstanceStore` are now marked `@internal`.

[Unreleased]: https://github.com/suhock/php-dependency-injection/compare/0.1.0...HEAD
