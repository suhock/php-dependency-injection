# Dependency Injection Library for PHP

The Five Two Dependency Injection library provides a highly customizable
dependency injection framework for projects running on PHP 8.1 or later. This
library focuses on facilitating sound object-oriented practices, testing,
refactoring, and static analysis by emphasizing factory methods and class
constructors as the primary means of building complex dependencies. By design
the framework can only resolve and inject `object` type dependencies and does
not rely on configuration files or arbitrary identifiers for the container
specification.

```php
$container = new FiveTwo\DependencyInjection\Container();
$container->addSingletonClass(MyApplication::class)
    // Add the rest of your dependencies...
    ->get(MyApplication::class)
    ->run();
```

Out of the box, this library provides [singleton](#singleton) and
[transient](#transient) lifetime strategies and a variety ways of
[provisioning instances](#adding-dependencies-to-the-container) of specific
types, as well as specifying factories for all classes in a particular
[namespace](#namespace-container) or implementing a specific
[interface](#interface-container). You can easily extend the default `Container`
implementation with your own custom lifetime strategies, instance providers, or
nested containers to fit your needs.

The library also provides a [`ContextContainer` class](#context-container) for
cascading dependency resolution down a nested context hierarchy and an
[`Injector` class](#dependency-injector) for injecting dependencies and explicit
parameters into a specific function or constructor.

## Table of Contents

- [Installation](#installation)
- [Basic usage](#basic-usage)
- [Instance lifetime](#instance-lifetime)
    - [Singleton](#singleton)
    - [Transient](#transient)
- [Adding dependencies to the container](#adding-dependencies-to-the-container)
    - [Autowire a class](#autowire-a-class)
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
- [Context Container](#context-container)
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

Add `fivetwo/dependency-injection` to the `require` section of your project's
`composer.json` file.

```json
{
    "require": {
        "fivetwo/dependency-injection": "^0.1"
    }
}
```

Alternatively, use the command line from your project's root directory.

```shell
composer require "fivetwo/dependency-injection"
```

## Basic Usage

The basic `Container` class contains methods for building the container and
retrieving instances. Start by constructing an instance.

```php
use FiveTwo\DependencyInjection\Container;

$container = new Container();
```

Next, build your container, i.e., tell the container how it should resolve
specific dependencies in your application.

```php
$container
    // Autowire the constructor
    ->addSingletonClass(MyApplication::class)

    // Manually construct an instance with factory
    ->addSingletonFactory(Logger::class, fn () => new FileLogger('myapp.log'))

    // Alias an interface to an implementing type
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)

    // Add optional values with a mutator after autowiring the constructor
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

The container will autowire the class constructor and provide your application
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
instance of a class. There are two builtin lifetime strategies for classes:
singleton and transient. You can also add your own custom
[lifetime strategies](#custom-lifetime-strategies).

#### Singleton

Singleton instances are persisted for the lifetime of the container. When the
container receives a request for a singleton instance for the first time, it
will call the factory that you specified for that class, store the result, and
then return it. Any time the container receives a subsequent request for that
class, it will return that same instance. The default `Container` provides
convenience methods for adding singleton factories, all starting with the prefix
`addSingleton`.

#### Transient

Transient instances are never persisted and the container provides a fresh
value each time an instance is requested. Each time the container receives a
request for a transient instance, it will call the factory you specified for
that class. The default `Container` provides convenience methods for adding
transient factories, all starting with the prefix `addTransient`.

### Adding dependencies to the container

There are a number of built-in ways to specify how new instances should be
created.

 - [Autowire a class](#autowire-a-class)
 - [Map an interface to an implementation](#map-an-interface-to-an-implementation)
 - [Call a factory method](#call-a-factory-method)
 - [Provide a specific instance](#provide-a-specific-instance)

If needed, can also specify your own custom
[instance providers](#custom-instance-providers).

This document uses a modified PHP syntax for conveying API information.

#### Autowire a class

The container will construct classes by calling the class's constructor,
automatically resolving any dependencies in the constructor's parameter list.

If the class has any methods with an `Autowire` attribute, the container will
call those methods, resolving and injecting any dependencies listed in the
parameter list.

The optional `$mutator` callback allows additional configuration of the object
after the container has initialized it. The callback must take an instance of
the class as its first parameter. Additional parameters will be autowired.

```php
callable<TClass> Mutator(TClass $instance, [object|null ...]): void;

class Container
{
    function addSingletonClass<TClass>(
        string<TClass> $className,
        Mutator<TClass>|null $mutator = null
    ): static;

    function addTransientClass<TClass>(
        string<TClass> $className,
        Mutator<TClass>|null $mutator = null
    ): static;
}
```

##### Examples

###### Autowiring a class constructor

In the following example, when the container provides an instance of `MyService`
it will automatically inject all dependencies into its constructor to create an
instance.

```php
$container->addSingletonClass(MyService::class);
```

###### Using mutators to set optional properties

When the container provides instances of `CurlHttpClient`, after autowiring the
constructor, it will also set its `logger` property.

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
`setLogger()` has an `Autowire` attribute and call it passing in a `Logger`
instance resolved from the container.

```php
use FiveTwo\DependencyInjection\Autowire;

class CurlHttpClient
{
    #[Autowire]
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
    function addSingletonImplementation<TClass, TImpl of TClass>(
        string<TClass> $className,
        string<TImpl> $implementationClassName
    ): static;

    function addTransientImplementation<TClass, TImpl of TClass>(
        string<TClass> $className,
        string<IImpl> $implementationClassName
    ): static;
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
then autowire the `CurlHttpClient` constructor to provide an instance.

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
autowiring its constructor. If your application instead requests an instance of
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
method. Any parameters in the factory method will be autowired.

```php
callable<TClass> FactoryMethod([object|null ...]): TClass;

class Container
{
    function addSingletonFactory<TClass>(
        string<TClass> $className,
        FactoryMethod<TClass> $factory
    ): static;

    function addTransientFactory<TClass>(
        string<TClass> $className,
        FactoryMethod<TClass> $factory
    ): static;
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
    function addSingletonInstance<TClass>(
        string<TClass> $className,
        TClass $instance
    ): static;
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
configured namespace. By default, the namespace container will autowire the
constructor for all classes in the namespace.

The namespace container accepts an optional `$factory` parameter that specifies
a method which provides instances of classes in the namespace. The factory must
take the name of the class being instantiated as the first parameter. The outer
container will provide any additional dependencies.

```php
callable ClassFactory<TClass>(
    string<TClass> $className,
    object|null ...$dependencies
): TClass;

class Container
{
    function addSingletonNamespace(
        string $namespace,
        ClassFactory|null $factory = null
    ): static;

    function addTransientNamespace(
        string $namespace,
        ClassFactory|null $factory = null
    ): static;
}
```

##### Examples

```php
$container->addSingletonNamespace('Http');

/*
 * The container will provide an instance of CurlHttpClient by autowiring the
 * constructor because the class is in the Http namespace.
 */
$curlClient = $container->get(Http\CurlHttpClient::class);

$container->addSingletonImplementation(
    Http\HttpClient::class,
    Http\CurlHttpClient::class
);

/*
 * The container will know to autowire CurlHttpClient for HttpClient because we
 * specified the interface-implementation mapping.
 */
$httpClient = $container->get(Http\HttpClient::class);
```

#### Interface container

Interface containers provide an instance of the requested class if it is a
subclass of the specified interface or base class. Instances are acquired from
the given factory, or by autowiring the constructor if no factory is provided.
The factory must take the class name as the first parameter. The outer container
will provide any additional dependencies.

```php
callable<TClass> ImplementationFactory<TImpl of TClass>(
    string<TImpl> $className,
    [object|null ...]
): TImpl;

class Container
{
    function addSingletonInterface<TInterface>(
        string<TInterface> $className,
        ImplementationFactory<TClass>|null $factory = null
    ): static;

    function addTransientInterface<TInterface>(
        string<TInterface> $className,
        ImplementationFactory<TClass>|null $factory = null
    ): static;
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
autowiring the constructor if no factory is provided. The factory must take the
class name as the first parameter and an attribute instance as the second.
The outer container will provide any additional dependencies.

```php
callable<TAttr> AttributeClassFactory<TClass>(
    string<TClass> $className,
    TAttr $attributeInstance,
    [object|null ...]
): TClass;

class Container
{
    function addSingletonAttribute<TAttr>(
        string<TAttr> $attributeName,
        AttributeClassFactory<TAttr>|null $factory = null
    ): static;

    function addTransientAttribute<TAttr>(
        string<TAttr> $attributeName,
        AttributeClassFactory<TAttr>|null $factory = null
    ): static;
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

Implement `LifetimeStrategy` and optionally extend `Container` with convenience
methods for your new lifetime strategy.

#### Custom Instance Providers

Implement `InstanceProvider` and add it to your container using one of the basic
add methods. You can also extend `Container` to add convenience methods for
using your new instance provider.

```php
class Container
{
    public function add<TClass>(
        string<TClass> $className,
        LifetimeStrategy<TClass> $lifetimeStrategy,
        InstanceProvider $instanceProvider
    ): static;

    public function addSingleton<TClass>(
        string<TClass> $className,
        InstanceProvider $instanceProvider
    ): static;

    public function addTransient<TClass>(
        string<TClass> $className,
        InstanceProvider $instanceProvider
    ): static;
}
```

#### Custom Nested Containers

Implement `ContainerInterface` and pass into the container using one of the
methods below. If your custom container needs to be able to autowire objects,
you can pass in the outer container to its constructor.

```php
callable LifetimeStrategyFactory<TClass>(
    string<TClass> $className
): LifetimeStrategy<TClass>;

class Container
{
    public function addContainer(
        ContainerInterface $container,
        LifetimeStrategyFactory $lifetimeStrategyFactory
    ): static;

    public function addSingletonContainer(
        ContainerInterface $container
    ): static;

    public function addTransientContainer(
        ContainerInterface $container
    ): static;
}
```

## Context Container

The `ContextContainer` class provides a collection of named containers
(contexts) that can be used for providing different construction for the same
class in different parts of your application. Contexts can be named with strings
or enum values.

The context container utilizes a context stack for resolving dependencies. The
stack can be managed by the `push()` and `pop()` methods, or using the `Context`
attribute on class, function, or parameter declarations.

```php
use FiveTwo\DependencyInjection\Context\ContextContainerFactory;
use FiveTwo\DependencyInjection\Context\Context;

/*
 * Strings or enums can be used as identifiers for contexts. To help ease
 * analysis and future refactorings, enums or string-typed constants are
 * recommended.
 */
enum MyContexts {
    case Default;
    case Admin;
}

$container = ContextContainerFactory::createForDefaultContainer();

/*
 * Build the Default context's container.
 */
$container->context(MyContexts::Default)
    ->addSingletonClass(MyApplication::class)
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('default.json')
    );

/*
 * Build the Admin context's container.
 */
$container->context(MyContexts::Admin)
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('admin.json')
    );

$container
    /*
     * Make Default the default, fallback context.
     * Stack: Default
     */
    ->push(MyContexts::Default)

    /*
     * Fetch the application and run it.
     */
    ->get(MyApplication::class)
    ->run();

/*
 * Stack: Default, Admin
 *
 * The container will search the Admin context then the Default context for
 * each dependency in the following class.
 */
#[Context(MyContexts::Admin)]
class AdminEditDefaultSettingsController {
    /*
     * Stack: Default, Admin
     *
     * Since no context is explicitly specified, the stack is inherited as-is
     * from the class.
     */
    public function __construct(
        /*
         * Stack: Default, Admin
         *
         * The container will resolve $settings using the Settings factory in
         * the Admin context, since Admin is at the top of the context stack.
         */
        private readonly Settings $settings,

        /*
         * Stack: Default, Admin, Default
         *
         * The container will resolve $defaultSettings using the Settings
         * factory in the Default context, since the attribute below will
         * place Default at the top of the context stack for this parameter.
         */
        #[Context(MyContexts::Default)]
        private readonly Settings $defaultSettings,

        /*
         * Stack: Default, Admin
         *
         * The container will first attempt to resolve $httpClient using the
         * Admin context. However, since HttpClient does not exist in the
         * the Admin context, the container will resolve it using the factory
         * in the Default context.
         */
        private readonly HttpClient $httpClient
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
callable<TResult of mixed> InjectableFunction([... mixed]): TResult;

class Injector
{
    public function call<TResult>(
        InjectableFunction<TResult> $function,
        array<int|string, mixed> $params = []
    ): TResult;
    
    public function instantiate<TClass>(
        string<TClass> $className,
        array<int|string, mixed> $params = []
    ): TClass;
}
```

### Example

The following is an example where dependencies need to be injected into a
function in a controller instead of the constructor.

```php
use FiveTwo\DependencyInjection\Container;
use FiveTwo\DependencyInjection\Injector;

// Create the container and build it
$container = new Container();
// ... build the container ...

// Create an injector backed by the container
$injector = new Injector($container);

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
`array` types, it will autowire the constructor with the specified default
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
`HttpClient`. If it succeeds, it will check see 

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
    return $container ??= new Container();
}

/*
 * Build your container from the singleton container for now.
 * Replace with direct construction once refactoring is complete.
 */
$container = getAppContainer();
$container->addSingletonClass(MyApplication::class);
// ...
```
