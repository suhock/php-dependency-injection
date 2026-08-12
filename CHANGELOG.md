# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - Unreleased

Initial release.

### Added

- Compile-and-validate container: `ContainerBuilder::build()` compiles the
  entire dependency graph eagerly and reports every detected configuration
  error, including missing dependencies, captive dependencies, and cycles.
- Compiled resolution: the built container executes precomputed resolution
  plans with no reflection on the service-resolution path.
- Three service lifetimes: singleton, scoped, and transient, each configured
  through a single `add*()` method accepting a class, an implementing class
  name, a factory callback, or (for singletons) an object instance.
- Keyed services: every lifetime has an `addKeyed*()` variant, resolved with
  `get($className, $key)`.
- Scopes with deterministic disposal: scoped services live and are disposed
  with their `Scope`. Containers and scopes dispose the disposable services
  they created, in reverse creation order.
- Constructor autowiring with full support for union, intersection, and DNF
  parameter types.
- `#[Lazy]` attribute for deferring a dependency's construction until first
  use.
- Auto-binding of `ContainerInterface` and `ScopeFactoryInterface`, so low-level
  services can depend on the container or create scopes without configuration.
- Compilation caching: a configuration fingerprint keys the compiled
  dependency graph in a `CacheInterface` implementation (`ApcuCache`
  included), so unchanged configurations skip recompilation across processes.
- `ContainerBuilder::exportDependencyGraph()` for diagnostics and tooling.
- Standalone `Injector` for constructor and callable injection outside the
  container.
