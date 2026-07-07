# Dependency Injection Library for PHP

The PHP Dependency Injection library provides a customizable dependency
injection framework for projects running on PHP 8.1 or later.

```php
$container = Suhock\DependencyInjection\Container::createDefault();
$container->addSingletonClass(MyApplication::class)
    // Add the rest of your services...
    ->get(MyApplication::class)
    ->run();
```

Out of the box, this library provides [singleton](#singleton),
[scoped](#scoped), and [transient](#transient) lifetime strategies and a
variety ways of
[adding services](#adding-services-to-the-container) of specific
types, as well as specifying factories for all classes in a particular
[namespace](#namespace-container) or implementing a specific
[interface](#interface-container). You can also add more than one
implementation of the same type as [keyed services](#keyed-services). You can
easily extend the default `Container` implementation with your own custom
lifetime strategies, instance providers, or nested containers to fit your needs.

The library also provides an [`Injector` class](#dependency-injector) for
injecting dependencies and explicit parameters into a specific function or
constructor.

## Table of Contents

- [Installation](#installation)
- [Basic usage](#basic-usage)
- [Instance lifetime](#instance-lifetime)
    - [Singleton](#singleton)
    - [Scoped](#scoped)
    - [Transient](#transient)
- [Scopes](#scopes)
- [Adding services to the container](#adding-services-to-the-container)
    - [Inject a class](#inject-a-class)
    - [Map an interface to an implementation](#map-an-interface-to-an-implementation)
    - [Call a factory method](#call-a-factory-method)
    - [Provide a specific instance](#provide-a-specific-instance)
- [Nested containers](#nested-containers)
    - [Namespace container](#namespace-container)
    - [Interface container](#interface-container)
    - [Attribute container](#attribute-container)
- [Customizing the container](#customizing-the-container)
  - [Custom lifetime strategies](#custom-lifetime-strategies)
  - [Custom instance providers](#custom-instance-providers)
  - [Custom nested containers](#custom-nested-containers)
- [Keyed services](#keyed-services)
- [Dependency Injector](#dependency-injector)
- [Specifying dependencies](#specifying-dependencies)
  - [Named object types](#named-object-types)
  - [Nullable types](#nullable-types)
  - [Builtin types with default values](#builtin-types-with-default-values)
  - [Union types](#union-types)
  - [Intersection types](#intersection-types)
- [Appendix](#appendix)
  - [A note on service locators](#a-note-on-the-service-locator-pattern)
  - [Refactoring toward dependency injection](#refactoring-toward-the-dependency-injection-pattern)

## Installation

Add `suhock/dependency-injection` to the `require` section of your project's
`composer.json` file.

```json
{
    "require": {
        "suhock/dependency-injection": "^0.1"
    }
}
```

Alternatively, use the command line from your project's root directory.

```shell
composer require "suhock/dependency-injection"
```

## Basic Usage

The basic `Container` class contains methods for building the container and
retrieving instances. Start by constructing an instance.

```php
use Suhock\DependencyInjection\Container;

$container = Container::createDefault();
```

Next, build your container, i.e., tell the container how it should resolve
specific services in your application.

```php
$container
    // Inject the constructor's dependencies
    ->addSingletonClass(MyApplication::class)

    // Manually construct an instance with factory
    ->addSingletonFactory(Logger::class, fn () => new FileLogger('myapp.log'))

    // Alias an interface to an implementing type
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)

    // Add optional values with a mutator after injecting the constructor's dependencies
    ->addTransientClass(
        CurlHttpClient::class,
        function (CurlHttpClient $client, Logger $logger): void {
            $client->addLogger($logger);
        }
    );
```

Finally, call the `get()` method on the container to retrieve an instance of
your application and run it.

```php
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
        private readonly Logger $logger
    ) {
    }
}
```

If your application has other entry points (e.g. controllers), it might be
useful to inject the container into the part of your application that invokes
those entry points (e.g. a router).

```php
$container->addSingletonInstance(Container::class, $container);

class MyRouter
{
    public function __construct(
        private readonly Container $container
    ) {
    }

    public function routeRequest(string $method, string $path): void
    {
        $controllerClassName = $this->getControllerClassName($path);
        $controller = $this->container->get($controllerClassName);
        $controller->handleRequest($method);
    }
}
```

### Instance lifetime

The lifetime of an instance determines when the container should request a fresh
instance of a class. There are three builtin lifetime strategies for classes:
singleton, scoped, and transient. You can also add your own custom
[lifetime strategies](#custom-lifetime-strategies).

#### Singleton

Singleton instances are persisted for the lifetime of the container. When the
container receives a request for a singleton instance for the first time, it
will call the factory that you specified for that class, store the result, and
then return it. Any time the container receives a subsequent request for that
class — directly or through any [scope](#scopes) — it will return that same
instance. The default `Container` provides convenience methods for adding
singleton factories, all starting with the prefix `addSingleton`.

#### Scoped

Scoped instances are persisted for the lifetime of a [scope](#scopes) created
by `Container::createScope()`. Each scope receives its own instance the first
time it requests the class, and that instance's dependencies are resolved from
the scope, so scoped services can depend on other scoped services. Requesting a
scoped instance with no scope active — directly from the root container, or
from a singleton's dependency graph, which always resolves against the root —
throws a `ScopeException`. The default `Container` provides convenience methods
for adding scoped factories, all starting with the prefix `addScoped`.

#### Transient

Transient instances are never persisted and the container provides a fresh
value each time an instance is requested. Each time the container receives a
request for a transient instance, it will call the factory you specified for
that class. The default `Container` provides convenience methods for adding
transient factories, all starting with the prefix `addTransient`.

### Scopes

A scope represents a bounded unit of work — an HTTP request in a long-running
application server, a message pulled off a queue, a job in a worker. Create one
with `Container::createScope()`, resolve services from it as you would from the
container, and dispose it when the unit of work ends:

```php
$container = Container::createDefault()
    ->addSingleton(LoggerInterface::class, FileLogger::class)
    ->addSingletonClass(FileLogger::class)
    ->addScopedClass(RequestContext::class)
    ->addTransientClass(RequestHandler::class);

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
root container — so a singleton that depends on a scoped service fails with a
`ScopeException` instead of silently capturing one scope's instance.

`dispose()` releases the scope's cached instances; any further request to the
scope throws a `ScopeException`. Disposing a scope more than once has no
effect.

A service that needs to open scopes of its own should depend on
`ScopeFactoryInterface` rather than on the container. The default `Container`
implements this interface, so it can add itself:

```php
$container->addSingletonInstance(ScopeFactoryInterface::class, $container);

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

Note that [nested containers](#nested-containers) added with
`addSingletonContainer`/`addTransientContainer` (including namespace,
interface, and attribute containers) construct instances with their own
injector bound to the root container, so there are no `addScoped` variants of
these methods: classes they provide cannot have per-scope dependencies.

### Adding services to the container

There are a number of built-in ways to specify how new instances should be
created.

 - [Inject a class](#inject-a-class)
 - [Map an interface to an implementation](#map-an-interface-to-an-implementation)
 - [Call a factory method](#call-a-factory-method)
 - [Provide a specific instance](#provide-a-specific-instance)

If needed, can also specify your own custom
[instance providers](#custom-instance-providers).

#### Inject a class

The container will construct classes by calling the class's constructor,
automatically resolving any dependencies in the constructor's parameter list.

If the class has any methods with an `Inject` attribute, the container will
call those methods, resolving and injecting any dependencies listed in the
parameter list.

The optional `$mutator` callback allows additional configuration of the object
after the container has initialized it. The callback must take an instance of
the class as its first parameter. Additional parameters will be injected.

```php
class Container
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param (callable(TClass, mixed...): void)|null $mutator
     * @return $this
     */
    public function addSingletonClass(string $className, ?callable $mutator = null): static;

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param (callable(TClass, mixed...): void)|null $mutator
     * @return $this
     */
    public function addTransientClass(string $className, ?callable $mutator = null): static;
}
```

##### Examples

###### Injecting constructor dependencies

In the following example, when the container provides an instance of `MyService`
it will automatically inject all dependencies into its constructor to create an
instance.

```php
$container->addSingletonClass(MyService::class);
```

###### Using mutators to set optional properties

When the container provides instances of `CurlHttpClient`, after injecting the
constructor dependencies, it will also set its `logger` property.

```php
$container->addTransientClass(
    CurlHttpClient::class,
    function (CurlHttpClient $obj, Logger $logger): void {
        $obj->setLogger($logger);
    }
);
```

###### Using attributes to set optional properties

When the container provides an instance of `CurlHttpClient`, it will see that
`setLogger()` has an `Inject` attribute and call it passing in a `Logger`
instance resolved from the container.

```php
use Suhock\DependencyInjection\Inject;

class CurlHttpClient
{
    #[Inject]
    public function setLogger(Logger $logger): void {
        $this->logger = $logger;
    }
    
    // ...
}
```

#### Map an interface to an implementation

The container will provide classes by using the instance provider of the
specified implementing subclass. You must therefore also add the implementing
class to the container.

```php
class Container
{
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @param class-string<TClass> $className
     * @param class-string<TImplementation> $implementationClassName
     * @return $this
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): static;

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @param class-string<TClass> $className
     * @param class-string<TImplementation> $implementationClassName
     * @return $this
     */
    public function addTransientImplementation(string $className, string $implementationClassName): static;
}
```

##### Examples

###### Mapping an interface to a concrete implementation

```php
$container
    ->addSingletonImplementation(HttpClient::class, CurlHttpClient::class)
    ->addSingletonClass(CurlHttpClient::class);
```

When your application requests an instance of `HttpClient`, the container will
see that it should actually provide an instance of `CurlHttpClient`. It will
then inject the `CurlHttpClient` constructor's dependencies to provide an instance.

###### Chaining implementations

```php
$container
    ->addTransientImplementation(Throwable::class, Exception::class)
    ->addTransientImplementation(Exception::class, LogicException::class)
    ->addTransientClass(LogicException::class);
```

When your application requests an instance of `Throwable`, the container will
see that it should actually provide an instance of `Exception`. Next it will
see that instances of `Exception` should be created using `LogicException`.
Finally, it will provide an instance of `LogicException` for `Throwable` by
injecting its constructor's dependencies. If your application instead requests an instance of
`Exception` then the container will also provide an instance of
`LogicException`.

###### Unresolved mappings

The container must know how to provide the implementation or an exception will
be thrown:

```php
$container->addSingletonClass(HttpClient::class, CurlHttpClient::class);

/*
 * The container will throw an UnresolvedDependencyException because it does
 * not know how to provide an instance of CurlHttpClient.
 */
$container->get(HttpClient::class);
```

#### Call a factory method

The container will provide class instances by requesting them from a factory
method. Any parameters in the factory method will be injected.

```php
class Container
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param callable(mixed...): TClass $factory
     * @return $this
     */
    public function addSingletonFactory(string $className, callable $factory): static;

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param callable(mixed...): TClass $factory
     * @return $this
     */
    public function addTransientFactory(string $className, callable $factory): static;
}
```

##### Examples

###### Inject a configuration value

```php
$container->addSingletonFactory(
    Mailer::class,
    fn (AppConfig $config) => new Mailer($config->mailerTransport)
);
```

When your application requests an instance of `Mailer` from the container, it
will call the specified factory, injecting the `AppConfig` dependency. The
factory then manually constructs an instance, specifying the mailer transport
from that config.

###### Inline class implementation

```php
$container->addTransientFactory(
    Logger::class,
    fn (FileWriter $writer) => new class($writer) implements Logger {
        public function __construct(private readonly FileWriter $writer)
        {
        }

        public function log(string $message): void
        {
            $this->writer->writeLine($message);
        }
    }
);
```

#### Provide a specific instance

The container will provide a pre-constructed instance of a class.

```php
class Container
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param TClass $instance
     * @return $this
     */
    public function addSingletonInstance(string $className, object $instance): static;
}
```

##### Examples

###### Basic usage

```php
$request = new Request($_SERVER, $_GET, $_POST, $_COOKIE);
$container->addSingletonInstance(Request::class, $request);
```

Anytime your application requires a `Request` object, the container will provide
the exact same instance that was passed in with the `$request` variable.

### Nested containers

If the container cannot find a way to provide an instance of a specific class,
it will next check to see if there are any nested containers that can provide
the value. Two built-in nested container implementations are provided: namespace
and implementation. You can also add custom containers that implement
`ContainerInterface` using the `addContainer()` method. Nested containers are
searched sequentially in the order they are added.

#### Namespace container

Namespace containers provide an instance of the requested class if it is in the
configured namespace. By default, the namespace container will inject the
constructor's dependencies for all classes in the namespace.

The namespace container accepts an optional `$factory` parameter that specifies
a method which provides instances of classes in the namespace. The factory must
take the name of the class being instantiated as the first parameter. The outer
container will provide any additional dependencies.

```php
class Container
{
    /**
     * @param (callable(class-string, mixed...): object)|null $factory
     * @return $this
     */
    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static;

    /**
     * @param (callable(class-string, mixed...): object)|null $factory
     * @return $this
     */
    public function addTransientNamespace(string $namespace, ?callable $factory = null): static;
}
```

##### Examples

```php
$container->addSingletonNamespace('Http');

/*
 * The container will provide an instance of CurlHttpClient by injecting the
 * constructor's dependencies because the class is in the Http namespace.
 */
$curlClient = $container->get(Http\CurlHttpClient::class);

$container->addSingletonImplementation(
    Http\HttpClient::class,
    Http\CurlHttpClient::class
);

/*
 * The container will know to provide CurlHttpClient for HttpClient because we
 * specified the interface-implementation mapping.
 */
$httpClient = $container->get(Http\HttpClient::class);
```

#### Interface container

Interface containers provide an instance of the requested class if it is a
subclass of the specified interface or base class. Instances are acquired from
the given factory, or by injecting the constructor's dependencies if no factory is provided.
The factory must take the class name as the first parameter. The outer container
will provide any additional dependencies.

```php
class Container
{
    /**
     * @template TInterface of object
     * @param class-string<TInterface> $interfaceName
     * @param (callable(class-string<TInterface>, mixed...): TInterface)|null $factory
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static;

    /**
     * @template TInterface of object
     * @param class-string<TInterface> $interfaceName
     * @param (callable(class-string<TInterface>, mixed...): TInterface)|null $factory
     * @return $this
     */
    public function addTransientInterface(string $interfaceName, ?callable $factory = null): static;
}
```

##### Examples

The following example retrieves repository instances from a third-party
library's container.

```php
$container->addSingletonInterface(
    EntityNameProvider::class,
    /**
     * @template T of EntityNameProvider
     * @var class-string<T> $className
     * @return T
     */
    fn (string $className, EntityManager $em) =>
        $em->getRepository($className::getEntityName())
);

/*
 * The container will query the EntityManager for a UserRepository.
 */
$userRepository = $container->get(UserRepository::class);

class UserRepository extends EntityRepository implements EntityNameProvider
{
    public static function getEntityName(): string
    {
        return User::class;
    }
}
```

### Attribute container

Attribute containers will provide an instance of any class that has the
specified attribute. Instances are acquired from the given factory, or by
injecting the constructor's dependencies if no factory is provided. The factory must take the
class name as the first parameter and an attribute instance as the second.
The outer container will provide any additional dependencies.

```php
class Container
{
    /**
     * @template TAttribute of object
     * @param class-string<TAttribute> $attributeName
     * @param (callable(class-string, TAttribute, mixed...): object)|null $factory
     * @return $this
     */
    public function addSingletonAttribute(string $attributeName, ?callable $factory = null): static;

    /**
     * @template TAttribute of object
     * @param class-string<TAttribute> $attributeName
     * @param (callable(class-string, TAttribute, mixed...): object)|null $factory
     * @return $this
     */
    public function addTransientAttribute(string $attributeName, ?callable $factory = null): static;
}
```

##### Examples

The following example provides an alternative to the example under
[interface container section](#interface-container), using an attribute to
designate metadata rather than an interface.

```php
$container->addSingletonAttribute(
    EntityName::class,
    fn (string $className, EntityName $attribute, EntityManager $em) =>
        $em->getRepository($attribute->getName())
);

/*
 * The container will query the EntityManager for a UserRepository.
 */
$userRepository = $container->get(UserRepository::class);

#[EntityName(User::class)]
class UserRepository extends EntityRepository
{
}

#[Attribute(Attribute::TARGET_CLASS)]
class EntityName
{
    public function __construct(
        private readonly string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

### Customizing the Container

#### Custom Lifetime Strategies

Extend `LifetimeStrategy` and optionally extend `Container` with convenience
methods for your new lifetime strategy. `get()` receives the `ResolutionContext`
of the resolution root (the root container or a scope) alongside the instance
factory. A strategy that persists instances picks the context whose lifetime
matches — the given context, or `rootContext()` for container-wide caching —
then caches in that context's `InstanceStore`, keyed by the strategy itself, and
invokes the factory with that same context so the instance's dependencies come
from the root its lifetime is bound to. See `SingletonStrategy` and
`ScopedStrategy` for the two built-in examples of this pattern.

#### Custom Instance Providers

Implement `InstanceProviderInterface` and add it to your container using one of
the basic add methods. You can also extend `Container` to add convenience
methods for using your new instance provider.

```php
class Container
{
    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param LifetimeStrategy<TClass> $lifetimeStrategy
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider
    ): static;

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @return $this
     */
    public function addSingletonInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider
    ): static;

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @return $this
     */
    public function addTransientInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider
    ): static;
}
```

#### Custom Nested Containers

Implement `ContainerInterface` and pass into the container using one of the
methods below. If your custom container needs to be able to inject dependencies into objects,
you can pass in the outer container to its constructor.

```php
class Container
{
    /**
     * @param callable(class-string): LifetimeStrategy<object> $lifetimeStrategyFactory
     * @return $this
     */
    public function addContainer(
        ContainerInterface $container,
        callable $lifetimeStrategyFactory
    ): static;

    /**
     * @return $this
     */
    public function addSingletonContainer(ContainerInterface $container): static;

    /**
     * @return $this
     */
    public function addTransientContainer(ContainerInterface $container): static;
}
```

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
class Container
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
        string|object|null $source = null
    ): static;

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|(Closure(mixed...): TClass)|null $source
     * @return $this
     */
    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null
    ): static;

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
 - If any other object, the container provides that object directly.

Each explicit `add*` variant also has a keyed counterpart —
`addKeyedSingletonFactory()`, `addKeyedTransientClass()`,
`addKeyedScopedImplementation()`, and so on — and a custom lifetime strategy
and instance provider can be added under a key with `addKeyed()`.

### Examples

#### Adding and retrieving keyed services

```php
$container
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('default.json')
    )
    ->addKeyedSingleton(
        Settings::class,
        'admin',
        fn () => JsonSettings::fromFile('admin.json')
    );

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
        private readonly Settings $adminSettings
    ) {
    }
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
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\Injector;

// Create the container and build it
$container = Container::createDefault();
// ... build the container ...

// Create an injector backed by the container
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
        string $filter = ''
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
        private readonly HttpClient $httpClient
    ) {
    }
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
        private readonly ?HttpClient $httpClient
    ) {
    }
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
        private readonly array $otherOptions = []
    ) {
    }
}
```

In the example above, although the container cannot resolve `string`, `int`, or
`array` types, it will construct the class using the specified default
values. If you need to inject non-default values for builtin types, use a
[factory method](#call-a-factory-method).

### Union types

If a dependency is specified as a union type, the container will search
sequentially through all named object types in the union list. It will provide a
value using the first type it is able to resolve. Builtin types are ignored.

```php
class MyApplication
{
    public function __construct(
        private readonly HttpClient|GopherClient|string $client
    ) {
    }
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
        private readonly HttpClient&Serializable $httpClient
    ) {
    }
}
```

In the example above, the container will first attempt to resolve an instance of
`HttpClient`. If it succeeds, it will check whether that instance is also
`Serializable` and, if so, provide it. Otherwise, it will attempt to resolve
`Serializable` and check whether that instance is also an `HttpClient`. If
neither candidate satisfies both types, it will throw a
`ParameterResolutionException`.

## Appendix

### A note on the service locator pattern

The previous example resembles a service locator pattern. Please note that while
the `Container` class is functionally equivalent to a service locator, it is
usually best to avoid the service locator pattern, since it makes testing,
refactoring, and reasoning about your application more difficult.

Only places in your application that invoke entry points should directly use the
container. If you know the specific object type required before runtime, you
should rely on the container's automatic dependency injection capabilities
instead of directly invoking the container. In the prior example, the
application cannot know which container to invoke until it receives an actual
request, so injecting the container is necessary.

The following is an example of what not to do.

```php
/* Do NOT do this! */

class MyApiCaller
{
    public function __construct(
        private readonly Container $container
    ) {
    }

    public function callApi(): HttpResponse
    {
        $httpClient = $this->container->get(HttpClient::class);
        return $httpClient->get('https://www.example.com/api');
    }
}
```

In the above example, the required type, `HttpClient` is known before runtime
and can be requested directly by the constructor. This change will make
`MyClass`'s actual dependencies much clearer thereby making testing and
refactoring far easier.

```php
/* Do this instead! */

class MyApiCaller
{
    public function __construct(
        private readonly HttpClient $httpClient
    ) {
    }

    public function callApi(): HttpResponse
    {
        return $this->httpClient->get('https://www.example.com/api');
    }
}
```

### Refactoring toward the dependency injection pattern

An exception to the rule against the service locator pattern might be if you are
refactoring a legacy application toward the dependency injection pattern: you
want to reuse the container's dependency building logic as much as possible, but
there is still code where it is difficult to inject dependencies properly.

In this case, the application container can be built off a singleton instance
and made available to legacy code as an intermediate step. Once you finally
refactor all uses of the singleton container to use proper dependency injection,
the singleton container can be removed.

```php
/* Refactor this! */
public function myFragileBloatedFunction(...$args)
{
    // ...

    // $httpClient = new CurlHttpClient();
    // $logger = new FileLogger('myapp.log');
    // $httpClient->setLogger($logger);

    // Replaced the above duplicated construction logic with a call to the
    // container
    $httpClient = getAppContainer()->get(HttpClient::class);

    $result = $httpClient->get('https://www.example.com/api/' . $args[42]);
    // ...
}

/* Eliminate all references to the singleton container and remove this! */
function getAppContainer(): Container
{
    static $container;
    return $container ??= Container::createDefault();
}

/*
 * Build your container from the singleton container for now.
 * Replace with direct construction once refactoring is complete.
 */
$container = getAppContainer();
$container->addSingletonClass(MyApplication::class);
// ...
```
