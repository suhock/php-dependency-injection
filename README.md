# Five Two Dependency Injection Library for PHP

The Five Two Dependency Injection library provides a highly customizable
dependency injection framework for projects running on PHP 8.1 or later. This
library focuses on facilitating sound OOP practices, testing, refactoring, and
static analysis by emphasizing factory methods and class constructors as the 
primary means of building complex dependencies. By design the framework can only
resolve and inject `object`-typed dependencies and does not rely on
configuration files for the container specification.

```php
$container = (new FiveTwo\DependencyInjection\Container())
    ->addSingletonClass(MyApplication::class)
    // Add the rest of your dependencies...
    ->get(MyApplication::class)
    ->run();
```

Out of the box, this library provides [singleton](#singleton) and
[transient](#transient) [lifetime strategies](#lifetime-strategies) and a
variety of [instance providers](#adding-dependencies-to-the-container), in
addition to nested [namespace](#namespace-container) and
[interface](#interface-container) containers. You can easily extend the default
`Container` implementation with your own custom lifetime strategies, instance
providers, or nested containers to fit your needs.

The library also provides [context containers](#context-container) for cascading
dependency resolution down a nested context hierarchy.

## Table of Contents

- [Installation](#installation)
- [Basic Container Setup](#basic-container-setup)
- [Adding Dependencies to the Container](#adding-dependencies-to-the-container)
    - [Lifetime Strategies](#lifetime-strategies)
        - [Singleton](#singleton)
        - [Transient](#transient)
        - [Custom Strategies](#custom-strategies)
    - [Adding Dependencies to the Container](#adding-dependencies-to-the-container)
        - [Class Constructor Autowiring](#class-constructor-autowiring)
        - [Interface-Implementation Mapping](#interface-implementation-mapping)
        - [Factory Method Invocation](#factory-method-invocation)
        - [Object Instance Provision](#object-instance-provision)
    - [Nested Containers](#nested-containers)
        - [Namespace Container](#namespace-container)
        - [Interface Container](#interface-container)
- [Context Container](#context-container)
- [Dependency Injector](#dependency-injector)
- [Appendix](#appendix)
  - [API Syntax](#api-syntax)

## Installation

Add `fivetwo/dependency-injection` to the `require` section of your
application's `composer.json` file.

```json
{
    "require": {
        "fivetwo/dependency-injection": "*"
    }
}
```

## Basic Container Setup

The basic `Container` class contains methods for building the container and
retrieving instances.

```php
use FiveTwo\DependencyInjection\Container;

/*
 * Instantiate a container instance.
 */
$container = (new Container())
    /*
     * Add your main application class.
     */
    ->addSingletonClass(MyApplication::class)

    /*
     * Add the rest of your application's dependencies.
     */
    ->addSingletonFactory(Logger::class, fn () => new FileLogger('myapp.log'))
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)
    ->addTransientClass(
        CurlHttpClient::class,
        function (CurlHttpClient $client, Logger $logger): void {
            $client->addLogger($logger);
        }
    )

    /*
     * Fetch your main application class from the container and run it.
     */
    ->get(MyApplication::class)
    ->run();
```

### Lifetime Strategies

A lifetime strategy determines when the container should request a fresh
instance of a class from its
[instance provider](#adding-dependencies-to-the-container). There are two
builtin lifetime strategies for classes: singleton and transient.

#### Singleton

When a singleton strategy receives a request for a class for the first time, it
calls the class's instance provider, stores the result, and returns it. All
subsequent requests for that class return that same instance for the lifetime of
the container. The default `Container` provides convenience methods starting
with the prefix `addSingleton` for adding singleton factories.

#### Transient

Each time a transient strategy receives a request for a class, it calls the
class's instance provider and returns the result. The transient strategy does
not restore the result and will request a new instance from the factory each
time it is called. The default `Container` provides convenience methods starting
with the prefix `addTransient` for adding transient factories.

#### Custom Strategies

Custom lifetime strategies can also be added by extending the
`LifetimeStrategy` class.

### Adding Dependencies to the Container

Instance providers object instances when required by the class's lifetime
strategy. The default `Container` provides convenience methods for each of the
built-in instance providers.

For more information on the syntax used in this document for API information,
please refer to the [API Syntax](#api-syntax) appendix.

#### Class Constructor Autowiring

The container will construct classes by calling the class's constructor,
resolving any dependencies in the constructor's parameter list.

The optional `$mutator` callback allows additional configuration of the object
after the container has instantiated it. The callback must take an instance of
the class as its first parameter. Additional parameters will be autowired.

```php
callable Mutator<TClass>(
    TClass $instance,
    object|null ...$dependencies
): void;

class Container {
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

***Examples:***

```php
$container->addSingletonClass(MyService::class);

/*
 * The container will provide an instance of MyService by autowiring the
 * constructor.
 */
$service = $container->get(MyService::class);
```

Using mutators to set optional properties:

```php
$container->addTransientClass(
    CurlHttpClient::class,
    function (CurlHttpClient $obj, Logger $logger): void {
        $obj->setLogger($logger);
    }
);

/*
 * The container will provide an instance of CurlHttpClient with its logger
 * property set.
 */
$httpClient = $container->get(CurlHttpClient::class);
```

#### Interface-Implementation Mapping

The container will provide classes by using the instance provider of the
specified implementing subclass. You must therefore also add the implementing
class to the container.

```php
class Container {
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

***Examples:***

```php
$container
    ->addSingletonImplementation(HttpClient::class, CurlHttpClient::class)
    ->addSingletonClass(CurlHttpClient::class);

/*
 * The container will provide an instance of CurlHttpClient for HttpClient.
 */
$httpClient = $container->get(HttpClient::class);
```

Implementations can be chained:

```php
$container
    ->addTransientImplementation(Throwable::class, Exception::class)
    ->addTransientImplementation(Exception::class, LogicException::class)
    ->addTransientClass(LogicException::class);

/*
 * The container will provide an instance of LogicException for Throwable.
 */
$exception = $container->get(Throwable::class);
```

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

#### Factory Method Invocation

The container will provide class instances by requesting them from a factory
method. Any parameters in the factory method will be autowired.

```php
callable Factory<TClass>(object|null ...$dependencies): TClass|null;

class Container {
    function addSingletonFactory<TClass>(
        string<TClass> $className,
        Factory<TClass> $factory
    ): static;

    function addTransientFactory<TClass>(
        string<TClass> $className,
        Factory<TClass> $factory
    ): static;
}
```

***Examples:***

```php
$container->addSingletonFactory(
    Mailer::class,
    fn (AppConfig $config) => new Mailer($config->mailerTransport)
);

/*
 * The container will provide an instance of Mailer with the transport set to
 * the value retrieved from AppConfig.
 */
$container->get(Mailer::class);

/*
 * You can use factory methods to provide an inline class implementation.
 */
$container->addTransientFactory(
    Logger::class,
    fn (FileWriter $writer) => new class($writer) implements Logger {
        public function __construct(FileWriter $writer) { /* ... */ }
        /* ... */
    }
);
```

#### Object Instance Provision

The container will provide a single, pre-specified instance of a class.

```php
class Container {
    function addSingletonInstance<TClass>(
        string<TClass> $className,
        TClass|null $instance
    ): static;
}
```

***Examples:***

```php
$request = new Request($_GET, $_POST);
$container->addSingletonInstance(Request::class, $request);

/*
 * The container will provide the same instance that was provided to it above.
 */
$fetchedRequest = $container->get(Request::class);
var_dump($fetchedRequest === $request); // true
```

### Nested Containers

The default `Container` will also search nested instances of
`ContainerInterface` in the order they are added for instances of a dependency
if no factory for a given class exists in the outer container. Two nested
container implementations are provided: namespace and implementation.

#### Namespace Container

Namespace containers provide an instance of the requested class if it is in the
configured namespace. By default, the namespace container will autowire the
constructor for all classes in the namespace.

The namespace container accepts an optional `$factory` parameter that specifies
a method which provides instances of classes in the namespace. The factory must
take the name of the class being instantiated as the first parameter. Additional
parameters will be autowired from the outer container.

```php
callable ClassFactory<TClass>(
    string<TClass> $className,
    object|null ...$dependencies
): TClass|null;

class Container {
    function addSingletonNamespace(
        string $namespace,
        ClassFactory<TClass>|null $factory = null
    ): static;

    function addTransientNamespace(
        string $namespace,
        ClassFactory<TClass>|null $factory = null
    ): static;
}
```

***Examples:***

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

#### Interface Container

Interface containers provide an instance of the requested class if it is a
subclass of the specified interface or base class. Instances are acquired from
the given factory, or by autowiring the constructor if no factory is provided.
The factory must take the class name as the first parameter. Additional
parameters will be injected.

```php
callable ClassFactory<TClass>(
    string<TClass> $className,
    object|null ...$dependencies
): TClass|null;

class Container {
    function addSingletonInterface<TClass>(
        string<TClass> $className,
        ClassFactory<TImpl of TClass>|null $factory = null
    ): static;

    function addTransientInterface<TClass>(
        string<TClass> $className,
        ClassFactory<TImpl of TClass>|null $factory = null
    ): static;
}
```

***Examples:***

```php
$container
    ->addSingletonInterface(
        EntityNameProvider::class,
        fn (string $className, EntityManager $em) =>
            $em->getRepository($className::getEntityName())
    )
    ->addSingletonClass(MyRepository::class);

/*
 * The container will query the EntityManager for a UserRepository.
 */
$userRepository = $container->get(UserRepository::class);

/*
 * The container will directly construct a MyRepository instance since it has a
 * specific constructor instance provider specified.
 */
$myRepository = $container->get(MyRepository::class);
```

Using a factory method:

```php
$container->addTransientInterface(
    HttpClient::class,
    function (string $className, Logger $logger): HttpClient {
        $client = new $className();
        $client->addLogger($logger);

        return $client;
    }
);

/*
 * The container will provide an instance of CurlHttpClient with a logger
 * added.
 */
$httpClient = $container->get(Http\CurlHttpClient::class);
```

### Customizing

#### Custom Lifetime Strategies

Implement `LifetimeStrategy`

***Example:***

```php
use FiveTwo\DependencyInjection\LifetimeStrategy;

class ScopedLifetimeStrategy implements LifetimeStrategy
{
    private ?object $instance = null;

    private
}
```

#### Custom Instance Providers

Implement `InstanceProvider`

```php
class Container {
    public function add<TClass>(
        string<TClass> $className,
        LifetimeStrategy<TClass> $lifetimeStrategy,
        InstanceProvider $instanceProvider
    ): static;
}
```

#### Custom Nested Containers

Implement `ContainerInterface`

## Context Container

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
     */
    ->push(MyContexts::Default)

    /*
     * Fetch the application and run it.
     */
    ->get(MyApplication::class)
    ->run();

/*
 * The container will search the Admin context then the Default context for
 * each dependency in the following class.
 */
#[Context(MyContexts::Admin)]
class AdminEditDefaultSettingsController {
    public function __construct(
        /*
         * The container will resolve $settings using the Settings factory in
         * the Admin context, since Admin is at the top of the context stack.
         */
        private readonly Settings $settings,

        /*
         * The container will resolve $defaultSettings using the Settings 
         * factory in the Default context, since the attribute below will 
         * place Default at the top of the context stack for this parameter.
         */
        #[Context(MyContexts::Default)]
        private readonly Settings $defaultSettings,

        /*
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
        map_query_to_param_assoc_array($_GET)
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

## Appendix

### API syntax

This library places a high emphasis on strict type safety, something for which
PHP is still in its infancy. Correspondingly, the existing syntax of PHP,
PHPDoc, and popular static analysis tools are lacking, especially when it comes
to expressing callable-scoped generics. This document utilizes a modified PHP
syntax inspired heavily by the C# and TypeScript approaches.

```php
function exampleFunction<TClass>(string<TClass> $className): TClass;
```

The above `exampleFunction` takes a generic type `TClass` (in this document, all
generics are implicitly `object` types). The function takes a `string`,
`$className`, which contains the fully qualified name of the class (i.e.,
`TClass::class`). The function will return a value of type `TClass`.

```php
callable ExampleFunctionType<TClass, TSubclass of TClass>(
    string<TClass> $className,
    string<TSubclass> $subclassName
): void;

function anotherFunction<TClass, TSubclass of TClass>(
    string<TClass> $className,
    ExampleFunctionType<TClass, TSubclass> $callback
): void;
```

The above `callable` declaration is analogous to the `delegate` keyword in C#,
in this case defining a custom `callable` type, `ExampleFunctionType`. The
template takes two classes: `TClass` and another class `TSubclass` which must
be of type `TClass`. The custom type is then taken as a parameter in
`anotherFunction`.
