# Dependency Injection Library for PHP

A compile-and-validate dependency injection container for PHP 8.4+, built for
both long-running applications and per-request processes.

## Highlights

- **Validation** – Catch missing dependencies, cycles, captive scopes, and
  invalid factories when the container is built, with all detected defects
  reported together.
- **Service Lifetimes** – Use singleton, scoped, and transient lifetimes with
  deterministic disposal in reverse creation order.
- **Scoped Isolation** – Isolate request or job state in long-running workers
  with first-class scopes.
- **Keyed Services** – Register and inject multiple implementations of the same
  service type under distinct keys.
- **Native Lazy Objects** – Defer expensive services and break dependency
  cycles with native PHP lazy objects.
- **Caching and Diagnostics** – Cache compiled dependency plans for improved
  performance in per-request processes and export the dependency graph for
  tooling and analysis.
- **Flexible Integration** – Use the standalone dependency injector, optional
  PSR-11 adapter, and PHPStan extensions.

```php
$container = Suhock\DependencyInjection\ContainerBuilder::createDefault()
    ->addSingleton(MyApplication::class)
    ->addSingleton(Logger::class, fn () => new FileLogger('myapp.log'))
    ->addTransient(HttpClient::class, CurlHttpClient::class)
    ->addTransient(CurlHttpClient::class)
    ->build();

$container->get(MyApplication::class)->run();
```

## Table of Contents

- [Installation](#installation)
- [Compatibility](#compatibility)
- [Basic usage](#basic-usage)
- [Building the container](#building-the-container)
    - [Build performance](#build-performance)
    - [Graph diagnostics](#graph-diagnostics)
- [Instance lifetime](#instance-lifetime)
    - [Singleton](#singleton)
    - [Scoped](#scoped)
    - [Transient](#transient)
- [Scopes](#scopes)
    - [Auto-binding](#auto-binding)
    - [Example: FrankenPHP worker mode](#example-frankenphp-worker-mode)
- [Disposing services](#disposing-services)
- [Adding services to the container](#adding-services-to-the-container)
    - [Specify the class name](#specify-the-class-name)
    - [Specify an implementing class name](#specify-an-implementing-class-name)
    - [Provide a factory callback](#provide-a-factory-callback)
    - [Provide a concrete instance](#provide-a-concrete-instance)
- [Keyed services](#keyed-services)
- [Dependency Injector](#dependency-injector)
- [Specifying dependencies](#specifying-dependencies)
  - [Named object types](#named-object-types)
  - [Nullable types](#nullable-types)
  - [Builtin types with default values](#builtin-types-with-default-values)
  - [Union types](#union-types)
  - [Intersection types](#intersection-types)
  - [Lazy dependencies](#lazy-dependencies)
- [Error handling](#error-handling)
- [Caching reflected metadata](#caching-reflected-metadata)
- [Appendix](#appendix)
  - [PSR-11 compatibility](#psr-11-compatibility)
  - [PHPStan extensions](#phpstan-extensions)

## Installation

```shell
composer require "suhock/dependency-injection"
```

## Compatibility

The library requires PHP 8.4 or later and is tested on PHP 8.4 and 8.5.

The only required runtime dependency is the first-party `suhock/disposable`
package; there are no third-party runtime dependencies. The optional `ext-apcu`
extension enables persistent caching of reflected metadata; see
[Caching reflected metadata](#caching-reflected-metadata).

## Basic Usage

The `ContainerBuilder` class contains the methods for configuring the
container: the `add*` methods, `remove()`, and `configure()`. Its `build()`
method compiles that configuration into an immutable `Container`, which
provides `get()`, `has()`, `createScope()`, and `dispose()`. Start by
constructing a builder.

```php
use Suhock\DependencyInjection\ContainerBuilder;

$builder = ContainerBuilder::createDefault();
```

Next, configure the builder: tell it how it should resolve specific
services in your application.

```php
$builder
    // Inject the constructor's dependencies
    ->addSingleton(MyApplication::class)

    // Manually construct an instance with factory
    ->addSingleton(MyLogger::class, fn () => new FileLogger('myapp.log'))

    // Provide a pre-constructed instance and promise to dispose it later ourselves
    ->addSingletonInstance(RequestContext::class, $requestContext, shouldDispose: false)

    // Alias an interface to an implementing type
    ->addTransient(HttpClient::class, CurlHttpClient::class)

    // Take the autowired instance and configure it before handing it back
    ->addTransient(
        CurlHttpClient::class,
        function (CurlHttpClient $client, Logger $logger): CurlHttpClient {
            $client->addLogger($logger);

            return $client;
        }
    );
```

Finally, call `build()` to compile and validate the whole graph and obtain the
container, then call `get()` on it to retrieve an instance of your
application and run it. See [Building the container](#building-the-container)
for what `build()` checks and how a misconfiguration is reported.

```php
$container = $builder->build();

$container
    ->get(MyApplication::class)
    ->handleRequest();
```

The container will inject the constructor's dependencies and provide your application
the instance.

```php
class MyApplication
{
    // The constructor arguments will be provided by the container
    public function __construct(
        private readonly HttpClient $client,
        private readonly Logger $logger,
    ) {}
}
```

If your application has other entry points (e.g., controllers), it might be
useful to inject the container into the part of your application that invokes
those entry points (e.g., a router). There is nothing to add for this:
`ContainerInterface` auto-binds to the current resolution root, so a router
resolved from the container receives the container itself. See
[Scopes](#scopes) for the full auto-binding rules, including what a router
resolved from a scope receives instead.

```php
class MyRouter
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function routeRequest(string $method, string $path): void
    {
        $controllerClassName = $this->getControllerClassName($path);
        $controller = $this->container->get($controllerClassName);
        $controller->handleRequest($method);
    }
}
```

> [!WARNING]
> Reserve this for dispatchers that only know the required type at runtime.
> Everywhere else, inject the concrete dependency directly; pulling it from the
> container (the service locator pattern) makes code harder to test and refactor.

### Building the container

`ContainerBuilder::build(): Container` compiles the configured dependency
graph, validates it, and returns an immutable `Container`. Every service you
add must be resolvable. If the configuration has a defect, `build()` reports
it as an error rather than waiting until you request the service.

```php
$container = $builder->build();
```

If validation finds any guaranteed-failure defect, `build()` throws one
`Suhock\DependencyInjection\Validation\ContainerValidationException` carrying
every problem it found, not just the first:

```php
use Suhock\DependencyInjection\Validation\ContainerValidationException;

try {
    $container = $builder->build();
} catch (ContainerValidationException $e) {
    foreach ($e->getIssues() as $issue) {
        // $issue->kind, $issue->className, $issue->key, $issue->message
        echo $issue->kind->name . ': ' . $issue->serviceId() . ' - ' . $issue->message . "\n";
    }
}
```

The builder is still usable after a failed build: fix the configuration and
call `build()` again; each successful `build()` also produces a fully
independent `Container`.

`build()` reports every one of the following as a build error:

 - A required dependency that is not resolvable from the container, including
   a keyed dependency not added under that key.
 - An interface mapped to an implementation that is not itself a resolvable
   service.
 - A required parameter with a builtin type and no default value.
 - A factory whose declared return type can never satisfy the service class it
   was added for.
 - A service class that can never be instantiated: missing, abstract, or an
   interface.
 - A dependency cycle in which every edge is required, so no member of the
   cycle can ever construct.
 - A singleton that reaches a scoped service through required edges: a
   captive dependency (see [Scopes](#scopes)).
 - A `#[Lazy]` parameter the container cannot construct as a lazy object (see
   [Lazy dependencies](#lazy-dependencies)).

#### Build performance

Without a cache, `build()` recompiles and revalidates the whole graph every
time it is called. That is inexpensive for most applications, but on a
per-request lifecycle such as PHP-FPM you pay that cost on every request.
Supplying a `CacheInterface` (e.g. `ApcuCache`) lets `build()` store the
validated plans under a fingerprint of the configuration; rebuilding an
unchanged configuration loads the stored plans and skips compilation and
validation entirely:

```php
use Suhock\DependencyInjection\Cache\ApcuCache;
use Suhock\DependencyInjection\ContainerBuilder;

$container = ContainerBuilder::createDefault(new ApcuCache())
    ->addSingleton(MyApplication::class)
    ->build();
```

With APCu, each later `build()` of an unchanged configuration costs little more
than a hash and a cache lookup; the first build after a deploy or a
configuration change still pays for full compilation and validation. A
worker-mode runtime that builds once at boot (see
[FrankenPHP worker mode](#example-frankenphp-worker-mode)) pays that cost once
regardless of caching. The same cache also memoizes the reflected metadata used
by the injector; see [Caching reflected metadata](#caching-reflected-metadata).

#### Graph diagnostics

`exportDependencyGraph()` exports the dependency graph `build()` would produce
as plain data for external tooling: every service (including the
[auto-bound](#auto-binding) ones) and every satisfied dependency edge, with the
injection point each edge flows through and whether it is required.

```php
$graph = $builder->exportDependencyGraph();

// The graph roots (services nothing injects) are the ids no edge targets.
// They are typically the entry points your application resolves itself.
$targets = array_map(fn ($edge) => $edge->targetId, $graph->edges);
$roots = array_diff($graph->serviceIds, $targets);

// Or render it:
foreach ($graph->edges as $edge) {
    echo "\"$edge->sourceId\" -> \"$edge->targetId\";\n"; // Graphviz
}
```

The export mirrors what resolution would actually traverse. Unsatisfiable
injection points produce no edge (validation reports those); an
added-but-never-chosen union member gets no incoming edge; and dependencies
hidden inside factory bodies do not appear. `exportDependencyGraph()` never
throws, so a configuration that would fail `build()` still exports.

### Instance lifetime

The lifetime of an instance determines when the container should request a fresh
instance of a class. There are three lifetime strategies for classes:
singleton, scoped, and transient.

#### Singleton

Singleton instances are persisted for the lifetime of the container. When the
container receives a request for a singleton instance for the first time, it
will call the factory that you specified for that class, store the result, and
then return it. Any time the container receives a subsequent request for that
class, directly or through any [scope](#scopes), it will return that same
instance. The default `ContainerBuilder` provides convenience methods for
adding singleton factories, all starting with the prefix `addSingleton`.

#### Scoped

Scoped instances are persisted for the lifetime of a [scope](#scopes) created
by `Container::createScope()`. Each scope receives its own instance the first
time it requests the class, and that instance's dependencies are resolved from
the scope, so scoped services can depend on other scoped services. Requesting a
scoped instance with no scope active (directly from the root container, or
from a singleton's dependency graph, which always resolves against the root)
throws a `ScopeException`. The default `ContainerBuilder` provides convenience
methods for adding scoped factories, all starting with the prefix `addScoped`.

#### Transient

Transient instances are never persisted and the container provides a fresh
value each time an instance is requested. Each time the container receives a
request for a transient instance, it will call the factory you specified for
that class. The default `ContainerBuilder` provides convenience methods for
adding transient factories, all starting with the prefix `addTransient`.

### Scopes

A scope represents a bounded unit of work, such as an HTTP request in a
long-running application server, a message pulled off a queue, or a job in a
worker. Build the container once, then create a scope with
`Container::createScope()`, resolve services from it as you would from the
container, and dispose it when the unit of work ends:

```php
use Suhock\DependencyInjection\ContainerBuilder;

$container = ContainerBuilder::createDefault()
    ->addSingleton(LoggerInterface::class, FileLogger::class)
    ->addSingleton(FileLogger::class)
    ->addScoped(RequestContext::class)
    ->addTransient(RequestHandler::class)
    ->build();

$scope = $container->createScope();

try {
    // Both handlers share one RequestContext; the logger is the container-wide
    // singleton.
    $scope->get(RequestHandler::class)->handle();
    $scope->get(RequestHandler::class)->handle();
} finally {
    $scope->dispose();
}
```

Within a scope, services added with `addScoped*` methods resolve to one
instance per scope, and every dependency in their graph is resolved from the
scope, so transient services requested from a scope also receive the scope's
scoped instances. Singleton services resolve to the same instance no matter
which scope requests them, and their dependencies always resolve against the
root container. A singleton that depends on a scoped service therefore fails
with a `ScopeException` instead of capturing one scope's instance. When the
scoped service is required (reachable through required edges alone),
[build validation](#building-the-container) catches this as a
captive-dependency error before you ever call `get()`. A scoped service
requested with no active scope (for example from inside a factory body) is
something validation cannot predict, so it throws `ScopeException` at run time.

`dispose()` releases the scope's cached instances; any further request to the
scope throws a `ScopeException`. Disposing a scope more than once has no
effect.

#### Auto-binding

`ContainerInterface` and `ScopeFactoryInterface` are automatically added at
`build()`, unless your own configuration already provides them, so most
applications never add either explicitly:

 - `ContainerInterface` resolves to the *current resolution root*: a service
   resolved from a scope receives that scope, and a service resolved from the
   root container receives the container. A scoped service can therefore
   depend on `ContainerInterface` to look up further services scoped to the
   same unit of work, and a service resolved from the root always receives the
   root. See the router example in [Basic usage](#basic-usage).
 - `ScopeFactoryInterface` resolves to the root `Container` from any depth,
   even from inside a scope, since only the root can create new scopes.
 - Neither auto-bound service is ever disposed by the container, and `has()`
   reports both as present. Adding your own descriptor for either id wins over
   the automatic binding.

A service that needs to open scopes of its own should depend on
`ScopeFactoryInterface` rather than on the container:

```php
final class QueueWorker
{
    public function __construct(private readonly ScopeFactoryInterface $scopes)
    {
    }

    public function process(Message $message): void
    {
        $scope = $this->scopes->createScope();

        try {
            $scope->get(MessageHandler::class)->handle($message);
        } finally {
            $scope->dispose();
        }
    }
}
```

#### Example: FrankenPHP worker mode

Application servers such as [FrankenPHP](https://frankenphp.dev/docs/worker/)
keep the PHP process alive across many requests: the application (including
the container and its singletons) boots once, and each incoming request is
handled by a callback. Without the per-request teardown that PHP-FPM provided,
any request-specific state held by a long-lived service silently leaks into
subsequent requests. Creating a scope per request restores that isolation:
scoped services live exactly as long as the request.

```php
<?php
// public/worker.php

use Suhock\DependencyInjection\ContainerBuilder;

require dirname(__DIR__) . '/vendor/autoload.php';

// Built once, reused for every request this worker handles: build()'s
// compilation and validation cost is paid a single time, before the loop
// starts, not per request.
$container = ContainerBuilder::createDefault()
    ->addSingleton(FileLogger::class)
    ->addSingleton(Logger::class, FileLogger::class)
    // FrankenPHP refreshes the superglobals before each request.
    ->addScopedFactory(RequestContext::class, fn () => RequestContext::fromGlobals())
    ->addTransient(RequestHandler::class)
    ->build();

$handler = static function () use ($container): void {
    $scope = $container->createScope();

    try {
        $scope->get(RequestHandler::class)->handle();
    } finally {
        $scope->dispose();
    }
};

while (frankenphp_handle_request($handler)) {
    gc_collect_cycles();
}
```

Run it with:

```shell
frankenphp php-server --worker public/worker.php
```

Every `RequestHandler` and any service in its dependency graph receives the
current request's `RequestContext`; when `dispose()` runs, the scope's cached
instances are released (and any that implement
[`DisposableInterface`](#disposing-services) have their `dispose()` method
called), so nothing carries over into the next iteration of the loop. The same
pattern applies to any long-running runtime (a RoadRunner or Swoole worker, a
queue consumer, or a daemon), with the runtime's own receive loop in place of
`frankenphp_handle_request()`.

### Disposing services

A service that holds a resource (a database transaction, an open file, a
socket) often needs to release it deterministically when its lifetime ends,
rather than waiting for garbage collection. A service can implement
`DisposableInterface` to be notified:

```php
use Suhock\Disposable\DisposableInterface;

final class UnitOfWork implements DisposableInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function dispose(): void
    {
        $this->connection->rollBackIfActive();
    }
}
```

When a resolution root (the container or a scope) is disposed, it calls
`dispose()` on the disposable services **it created**, in reverse creation
order so that dependents are disposed before their dependencies. `build()`
itself constructs nothing, so only the services your application actually
resolves are ever disposed:

```php
use Suhock\DependencyInjection\ContainerBuilder;

$container = ContainerBuilder::createDefault()
    ->addScoped(Connection::class)
    ->addScoped(UnitOfWork::class)
    ->build();

$scope = $container->createScope();

try {
    $scope->get(UnitOfWork::class)->commit();
} finally {
    // Disposes UnitOfWork, then Connection.
    $scope->dispose();
}
```

The container disposes its own singletons (and any surviving transients it
created) when the container itself is disposed:

```php
$container = ContainerBuilder::createDefault()
    ->addSingleton(ConnectionPool::class) // implements DisposableInterface
    ->build();

// ... run the application ...

$container->dispose();
```

After a container is disposed, any further `get()`, `has()`, or `createScope()`
call throws a `ContainerDisposedException`. Disposing a container or scope more
than once has no effect.

#### Opting out of disposal

> [!CAUTION]
> By default the built container disposes every disposable instance it holds,
> including concrete instances you explicitly supply. When an instance's
> disposal is managed by something outside the container or if you intend to
> dispose it yourself, you should use `add*Instance()` and pass
> `shouldDispose: false`:

```php
// The pool is closed elsewhere; the container must not dispose it.
$builder->addSingletonInstance(ConnectionPool::class, $pool, shouldDispose: false);
```

#### Lifetime and ordering guarantees

 - **Scoped and singleton** disposables are always disposed when their scope or
   container is disposed.
 - **Transient** disposables are disposed only if they are still referenced
   when their resolution root is disposed; a transient the application has
   already discarded is left to normal garbage collection (implement
   `__destruct()` if it must always clean up). This tracking uses a `WeakMap`,
   so discarded transients never accumulate.
 - Disposal proceeds in **reverse creation order**. This relies on
   dependencies being constructed before their dependents, which holds for
   constructor and factory injection. A service that
   resolves further dependencies lazily (for example by holding the container
   or a `ScopeFactoryInterface` and calling `get()` after construction) can
   invert that order for the pair involved.
 - If a `dispose()` call throws, the remaining instances are still disposed and
   the first exception is rethrown once the sweep completes.

Scopes created from a container are managed by their own caller; dispose them
before disposing the container so that their scoped instances are swept.

### Adding services to the container

There are a number of built-in ways to specify how services should be resolved:

 - [Specify the class name](#specify-the-class-name)
 - [Specify an implementing class name](#specify-an-implementing-class-name)
 - [Provide a factory callback](#provide-a-factory-callback)
 - [Provide a concrete instance](#provide-a-concrete-instance)

One method per lifetime covers the first three, choosing the provider from the
type of `$source`:

```php
class ContainerBuilder
{
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|(Closure(mixed...): TClass)|TClass|null $source
     * @return $this
     */
    public function addSingleton(string $className, string|object|null $source = null): self;

    public function addScoped(string $className, string|Closure|null $source = null): self;

    public function addTransient(string $className, string|Closure|null $source = null): self;
}
```

A `callable` that is not a `Closure` — an array callable, or an invokable
object — cannot be told apart from the other source kinds, so pass those to
[`add*Factory()`](#provide-a-factory-callback). Use
[`addSingletonInstance()`](#provide-a-concrete-instance) to keep disposal
responsibility for a supplied instance.

#### Specify the class name

Omit the source and the container will construct the named class by calling the
class's constructor, automatically resolving any dependencies in the
constructor's parameter list.

##### Examples

###### Injecting constructor dependencies

In the following example, when the container provides an instance of `MyService`
it will automatically inject all dependencies into its constructor to create an
instance.

```php
$builder->addSingleton(MyService::class);
```

To do more than construct the class — decorate it, or configure it in a way the
constructor cannot express — take the service's own type as a factory parameter.
See [Taking the autowired instance](#taking-the-autowired-instance).

#### Specify an implementing class name

The container will provide the named service by resolving the named implementing
subclass in its place.

> [!IMPORTANT]
> You must also add the implementing class to the container as its own
> resolvable service, or `build()` will reject the configuration.

Pass the implementing class name as the source.

##### Examples

###### Mapping an interface to a concrete implementation

```php
$builder
    ->addSingleton(HttpClient::class, CurlHttpClient::class)
    ->addSingleton(CurlHttpClient::class);
```

When your application requests an instance of `HttpClient`, the container will
see that it should actually provide an instance of `CurlHttpClient`. It will
then inject the `CurlHttpClient` constructor's dependencies to provide an instance.

###### Chaining implementations

```php
$builder
    ->addTransient(Throwable::class, Exception::class)
    ->addTransient(Exception::class, LogicException::class)
    ->addTransient(LogicException::class);
```

When your application requests an instance of `Throwable`, the container will
see that it should actually provide an instance of `Exception`. Next it will
see that instances of `Exception` should be created using `LogicException`.
Finally, it will provide an instance of `LogicException` for `Throwable` by
injecting its constructor's dependencies. If your application instead requests an instance of
`Exception` then the container will also provide an instance of
`LogicException`.

###### Unresolved mappings

The container must know how to provide the implementation, or `build()` will
reject the configuration:

```php
$builder->addSingleton(HttpClient::class, CurlHttpClient::class);

/*
 * build() throws a ContainerValidationException because CurlHttpClient is
 * not itself added as a resolvable service. See "Building the container".
 */
$container = $builder->build();
```

#### Provide a factory callback

The container will resolve the named service by requesting it from the provided
factory callback method. Any parameters in the factory method will be resolved
automatically.

```php
class ContainerBuilder
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param callable(mixed...): TClass $factory
     * @return $this
     */
    public function addSingletonFactory(string $className, callable $factory): static;

    public function addScopedFactory(string $className, callable $factory): static;

    public function addTransientFactory(string $className, callable $factory): static;
}
```

> [!TIP]
> If providing a `Closure` the shorthand forms can be also used:
>
> ```php
> // equivalent to $builder->addSingletonFactory(MyService::class, fn () => new MyService('foo'));
> $builder->addSingleton(MyService::class, fn () => new MyService('foo'));
> ```

> [!CAUTION]
> If you need to provide a named function, you must use the `add{Lifetime}Factory`
> form, since the shorthand methods interpret string sources as class names.

##### Examples

###### Inject a configuration value

```php
$builder->addSingletonFactory(
    Mailer::class,
    fn (AppConfig $config) => new Mailer($config->mailerTransport),
);
```

When your application requests an instance of `Mailer` from the container, it
will call the specified factory, injecting the `AppConfig` dependency. The
factory then manually constructs an instance, specifying the mailer transport
from that config.

###### Inline class implementation

```php
$builder->addTransientFactory(
    Logger::class,
    fn (FileWriter $writer) => new class($writer) implements Logger {
        public function __construct(
            private readonly FileWriter $writer,
        ) {}

        public function log(string $message): void
        {
            $this->writer->writeLine($message);
        }
    }
);
```

###### Taking the autowired instance

A factory parameter that names the service the factory produces receives an
instance with its constructor autowired as usual. This lets a factory configure
or decorate a class without hand-writing its constructor arguments, preserving
build-time verifiability.

```php
$builder->addTransientFactory(
    CurlHttpClient::class,
    function (CurlHttpClient $client, Logger $logger): CurlHttpClient {
        $client->addLogger($logger);
        return $client;
    },
);
```

The parameter is matched on its exact declared type plus its key, so a keyed
service requires `#[Key]` on the parameter as well:

```php
$builder->addTransientFactory(
    CurlHttpClient::class,
    'api',
    function (#[Key('api')] CurlHttpClient $client, Logger $logger): CurlHttpClient {
        $client->addLogger($logger);
        return $client;
    },
);
```

A union or intersection type never matches, even if one of its members resolves
to the same service.

The instance is constructed once per resolution, so if several parameters name
the service they all receive the same object.

The factory's return value will always be treated as the resolved service, even
if it differs from the provided instance. Thus, a factory may wrap the instance
it was handed with a decorator inheriting from the named service type rather
than return it directly. The container will dispose the provided instance
normally at the end of its lifetime.

```php
final class RetryingConnection extends Connection
{
    public function __construct(private readonly Connection $inner) {}
}
```

> [!NOTE]
> If the factory instead discards the provided instance, garbage collection
> will likely clean up the instance before the container can dispose it itself.
> In this case, if there is anything the class must always clean up, it should
> implement `__destruct()`, following the disposable pattern.

#### Provide a concrete instance

The container will resolve the specified service to the provided class instance.

```php
class ContainerBuilder
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param TClass $instance
     * @return $this
     */
    public function addSingletonInstance(string $className, object $instance, bool $shouldDispose = true): static;
}
```

> [!NOTE]
> There is no `addScopedInstance` or `addTransientInstance`: a provided instance is a
> single object, so it can only be a container-wide singleton.

> [!TIP]
> If the default value of `$shouldDispose: true` does not need to be changed
> the shorthand forms can be used.
>
> ```php
> // equivalent to $builder->addSingletonInstance(MyService::class, $obj);
> $builder->addSingleton(MyService::class, $obj);
> ```

##### Examples

###### Basic usage

```php
$request = new Request($_SERVER, $_GET, $_POST, $_COOKIE);
$builder->addSingletonInstance(Request::class, $request);
```

Anytime your application requires a `Request` object, the container will provide
the exact same instance that was passed in with the `$request` variable.

## Keyed services

An application sometimes needs to provide the same type in more than one
configuration. For example, you might want a separate `Settings` object for
different areas of your application. Keyed services let you add multiple
factories for a class under distinct keys and then retrieve or inject a specific
one. Keys can be strings or enum values. To help ease analysis and future
refactorings, enums or string-typed constants are recommended.

A keyed service is resolved only by its exact key. If the container has no
service under the requested key, it will throw a `ClassNotFoundException`
rather than falling back to the unkeyed service. A class may have both an
unkeyed service and any number of keyed services; they are independent
of one another.

```php
class ContainerBuilder
{
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|(Closure(mixed...): TClass)|TClass|null $source
     * @return $this
     */
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): static;

    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null,
    ): static;

    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null,
    ): static;
}

class Container
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @return TClass
     */
    public function get(string $className, string|UnitEnum|null $key = null): object;

    public function has(string $className, string|UnitEnum|null $key = null): bool;
}
```

The `$source` parameter determines how the container provides the instance:

 - If `null`, the container injects the class's constructor dependencies.
 - If a class name, the container maps the class to that implementation, which
   must also be added to the container.
 - If a closure, the container calls it as a factory, injecting its parameters.
 - If any other object, the container provides that object directly. Only
   `addKeyedSingleton()` accepts one, since a single object can only be a
   container-wide singleton.

The explicit `add*Factory()` and `add*Instance()` variants have keyed
counterparts too: `addKeyedSingletonFactory()`, `addKeyedScopedFactory()`,
`addKeyedTransientFactory()`, and `addKeyedSingletonInstance()`.

### Examples

#### Adding and retrieving keyed services

```php
$container = $builder
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('default.json'),
    )
    ->addKeyedSingleton(
        Settings::class,
        'admin',
        fn () => JsonSettings::fromFile('admin.json'),
    )
    ->build();

// Resolves the unkeyed Settings service.
$settings = $container->get(Settings::class);

// Resolves the Settings service under the 'admin' key.
$adminSettings = $container->get(Settings::class, 'admin');
```

#### Injecting a keyed service

Apply the `Key` attribute to a constructor parameter to inject the service added
under a specific key. As with `get()`, the lookup is absolute: if the container
has no service under the key, it will throw a `ParameterResolutionException`.

```php
use Suhock\DependencyInjection\Key;

class AdminController
{
    public function __construct(
        /*
         * Resolved from the unkeyed Settings service.
         */
        private readonly Settings $settings,

        /*
         * Resolved from the Settings service under the 'admin' key.
         */
        #[Key('admin')]
        private readonly Settings $adminSettings,
    ) {}
}
```

## Dependency Injector

The library also provides a dependency injector, `Injector` that can be used for
directly calling constructors and functions, injecting any dependencies from a
container. The injector also lets you directly inject specific values for named
or indexed parameters.

```php
class Injector
{
    /**
     * @template TResult
     * @param callable(mixed...): TResult $function
     * @param array<int|string, mixed> $params
     * @return TResult
     */
    public function call(callable $function, array $params = []): mixed;

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param array<int|string, mixed> $params
     * @return TClass
     */
    public function instantiate(string $className, array $params = []): object;
}
```

### Example

The following is an example where dependencies need to be injected into a
function in a controller instead of the constructor.

```php
use Suhock\DependencyInjection\ContainerBuilder;
use Suhock\DependencyInjection\Injector;

// Configure and build the container
$container = ContainerBuilder::createDefault()
    // ... add services ...
    ->build();

// Create an injector backed by the built container
$injector = Injector::createDefault($container);

// Fetch the application router from the container
$router = $container->get(Router::class);

// Get the appropriate controller from the request path
$controller = $router->getControllerFromRequest($_SERVER);

// Call the controller's handleGet() method, injecting the indicated parameter
// values in addition to any additional dependencies in the parameter list.
$page = $injector
    ->call(
        $controller->handleGet(...),
        map_query_to_assoc_param_array($_GET)
    )

// Then, call the render() function on the return value.
$page->render();

class ProjectListController
{
    public function handleGet(
        // Parameter below will be injected from the container.
        ProjectRepository $projectRepository,

        // Parameter below will be populated from the value provided in the
        // $injector->call() parameter array. The default value will be used if
        // the key 'filter' is not present in the array.
        string $filter = '',
    ): PageInterface {
        $projects = $projectRepository->query($filter);

        return new ProjectListPage($projects);
    }
}

interface PageInterface {
    public function render(): void;
}
```

## Specifying dependencies

A function specifies its dependencies by listing them in its parameter list. A
class specifies its dependencies by listing them in the parameter list of its
constructor. Dependencies must be specified as either named object types, union
types, or intersection types.

### Named object types

If a dependency is specified as a named object type, the container will only
provide a value if it can resolve a factory for that type.

```php
class MyApplication
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}
}
```

In the example above, the container will attempt to resolve an instance of
`HttpClient`. If it cannot resolve `HttpClient` it will throw an
`ParameterResolutionException`.

### Nullable types

If the container cannot resolve a dependency, but the dependency is nullable,
then the container will provide a null value.

```php
class MyApplication
{
    public function __construct(
        private readonly ?HttpClient $httpClient,
    ) {}
}
```

In the example above, the container will attempt to resolve an instance of
`HttpClient`. If it cannot resolve `HttpClient` it will inject a `null` value
instead.

### Builtin types with default values

The container is not able to resolve builtin types. However, if the function or
class takes a builtin type and that parameter specifies a default value, the
default value will be used.

```php
class MyApplication
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $homeUrl = '',
        private readonly int $timeout = 0,
        private readonly array $otherOptions = [],
    ) {}
}
```

In the example above, although the container cannot resolve `string`, `int`, or
`array` types, it will construct the class using the specified default
values. If you need to inject non-default values for builtin types, use a
[factory callback](#provide-a-factory-callback).

### Union types

If a dependency is specified as a union type, the container will search
sequentially through all named object types in the union list. It will provide a
value using the first type it is able to resolve. Builtin types are ignored.

```php
class MyApplication
{
    public function __construct(
        private readonly HttpClient|GopherClient|string $client,
    ) {}
}
```

In the example above, the container will attempt to resolve an instance of
`HttpClient` first. If it cannot resolve `HttpClient`, it will attempt to
resolve an instance of `GopherClient`. If it cannot resolve `GopherClient`, it
will ignore `string` and then throw an `ParameterResolutionException`.

### Intersection types

If a dependency is specified as an intersection type, the container will attempt
to fetch an instance of each type in the list until it finds one that satisfies
all the types in the list. Since an instance must be retrieved in order to test
whether it is a match, the use of intersection types may be slow and could
have unintended consequences if the construction of any non-matching instances
have side effects.

```php
class MyApplication
{
    public function __construct(
        private readonly HttpClient&Serializable $httpClient,
    ) {}
}
```

In the example above, the container will first attempt to resolve an instance of
`HttpClient`. If it succeeds, it will check whether that instance is also
`Serializable` and, if so, provide it. Otherwise, it will attempt to resolve
`Serializable` and check whether that instance is also an `HttpClient`. If
neither candidate satisfies both types, it will throw a
`ParameterResolutionException`.

### Lazy dependencies

Marking a parameter with the `#[Lazy]` attribute defers construction of that
dependency until the injected object is first used, rather than when the
consuming service is built. The injected value is a PHP
[lazy object](https://www.php.net/manual/en/language.oop5.lazy-objects.php) of
the resolved type, so it is `instanceof` the declared type and otherwise
indistinguishable from an eagerly resolved instance. Its lifetime, identity, and
disposal are those of the underlying service; a lazy singleton is still a single
shared instance.

```php
use Suhock\DependencyInjection\Lazy;

class ReportController
{
    public function __construct(
        #[Lazy]
        private readonly PdfRenderer $renderer,
    ) {}
}
```

Here `PdfRenderer` (and everything it depends on) is not constructed when
`ReportController` is resolved, but the first time a property or method of
`$renderer` is accessed. `#[Lazy]` applies to constructor and factory
parameters, except for a factory parameter naming the service the factory
produces, which the container constructs directly.

Because a lazy dependency is not constructed while its consumer is, `#[Lazy]`
also breaks an otherwise-fatal construction cycle between two services: mark one
edge of the cycle lazy and both services resolve.

The container constructs the lazy object in one of two ways, chosen
automatically:

- a **ghost** when it constructs the service itself (an autowired class), which
  it initializes in place on first use;
- a **proxy** when a factory produces the service, which invokes the factory on
  first use and forwards to its result.

For the container to build either, the dependency's concrete class must be known
at build time and must declare at least one property (PHP has no state to defer
for a property-less class). A factory-produced service qualifies when its
registered class or its declared return type is a concrete class. When neither
holds, `build()` reports the `#[Lazy]` parameter as a build error rather than
resolving it eagerly.

The standalone [Dependency Injector](#dependency-injector) also honors `#[Lazy]`.
A proxy needs a concrete class up front, so the injector takes it from the
parameter's own type when it is a concrete class. For an interface-typed
parameter it asks the backing container for the concrete class it resolves to.
If the backing container cannot report it, or the service has no
statically known concrete class, the `#[Lazy]` parameter throws an
`InjectorException`.

## Error handling

Every exception the library throws implements
`Suhock\DependencyInjection\DependencyInjectionExceptionInterface`, so a single
`catch` block can handle any failure originating from the container or injector.

```php
use Suhock\DependencyInjection\DependencyInjectionExceptionInterface;

try {
    $app = $container->get(MyApplication::class);
} catch (DependencyInjectionExceptionInterface $e) {
    // Handle any dependency injection failure.
}
```

These exceptions all extend `RuntimeException`: they signal a container that
was misconfigured or misused, whether the problem shows up at build time or at
run time, not a bug inside the library itself.

The base class is `DependencyInjectionException`. Notable subclasses include:

 - `Validation\ContainerValidationException`: thrown by
   `ContainerBuilder::build()`, aggregating every guaranteed-failure
   configuration defect (see
   [Building the container](#building-the-container)). Unlike the rest of this
   list, it is a build-time error: fix the configuration and call `build()`
   again.
 - `ClassNotFoundException`: no service is registered for the requested class.
 - `CircularDependencyException`: a dependency cycle was detected. Cycles
   through ordinary descriptors are caught at build time as a
   `ContainerValidationException`, so at run time this means the cycle passed
   through a factory body.
 - `ScopeException`: a scoped service was requested with no active scope, or a
   disposed scope was used.
 - `ImplementationException`: a mapped implementation is not a subtype of the
   class it is mapped to.
 - `ParameterResolutionException`: the injector could not resolve a parameter.

When dependency-injection exceptions are chained through a resolution graph,
they are consolidated into a single message; the original exception remains
available via `getConsolidatedException()`. `ContainerValidationException`
does not chain a previous exception; its issue list carries every problem
found instead.

## Caching reflected metadata

To resolve dependencies, the container and injector reflect over constructor and
method signatures. This reflected metadata can be memoized so it survives
between requests instead of being recomputed each time.

`ContainerBuilder::createDefault()` and `Injector::createDefault()` each accept
an optional cache:

```php
ContainerBuilder::createDefault(?CacheInterface $cache = null): self

Injector::createDefault(ContainerInterface $container, ?CacheInterface $cache = null): self
```

The same `CacheInterface` instance backs two independent things: the reflected
metadata memoized here, and `build()`'s compiled-graph reuse described in
[Build performance](#build-performance); supplying one cache to
`ContainerBuilder::createDefault()` gets you both.

`Suhock\DependencyInjection\Cache\CacheInterface` is a minimal key/value store
with two methods:

```php
interface CacheInterface
{
    public function tryGet(string $id, mixed &$value): bool;

    public function set(string $id, mixed $value): void;
}
```

`tryGet()` returns whether the id was present and populates `$value` by
reference. Reporting presence through the return value means a stored `null` or
`false` is not mistaken for a miss.

`Suhock\DependencyInjection\Cache\ApcuCache` implements `CacheInterface` using
the APCu extension. Its entries live in shared memory and persist across
requests served by the same worker pool. The constructor throws a
`RuntimeException` if the `apcu` extension is not loaded and enabled (on the
CLI, `apc.enable_cli` must be set), and it requires the optional `ext-apcu`
extension.

```php
use Suhock\DependencyInjection\Cache\ApcuCache;
use Suhock\DependencyInjection\ContainerBuilder;

$builder = ContainerBuilder::createDefault(new ApcuCache());
```

Consumers can also implement `CacheInterface` themselves to back the cache with
another store.

## Appendix

### PSR-11 compatibility

This library's `Container::get()` takes a class name and optional key rather
than PSR-11's opaque string id, so `Container` does not and should not
implement `Psr\Container\ContainerInterface` directly.

For frameworks that expect a PSR-11 container, the
[`suhock/dependency-injection-psr11`](https://github.com/suhock/php-dependency-injection-psr11)
package provides a thin adapter that wraps the container and translates its
exceptions into their PSR-11 counterparts.

```shell
composer require "suhock/dependency-injection-psr11"
```

### PHPStan extensions

[PHPStan](https://phpstan.org/) extensions for this library are published in the separate
[`suhock/dependency-injection-phpstan`](https://github.com/suhock/php-dependency-injection-phpstan)
package. Add it as a dev requirement.

```shell
composer require --dev "suhock/dependency-injection-phpstan"
```
