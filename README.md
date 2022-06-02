# Five Two Dependency Injection Library for PHP

The Five Two Dependency Injection library provides a highly customizable dependency injection
framework for projects running on PHP 8.1 or later. This library focuses on facilitating sound OOP
practices, testing, refactoring, and static analysis by emphasizing factory methods as the primary 
means of building complex dependencies. Thus, by design the framework can only resolve and 
inject ``object` dependencies and does not rely on configuration files for setting up dependencies.

```php
$container = (new FiveTwo\DependencyInjection\Container())
    ->addSingletonClass(MyApplication::class)
    // Add the rest of your dependencies...
    ->get(MyApplication::class)
    ->run();
```

Out of the box, the library provides [singleton](#singleton) and [transient](#transient)
[lifetime strategies](#lifetime-strategies) and a variety of
[instance providers](#instance-providers), in addition to nested 
[namespace](#namespace-container) and [interface implementation](#implementation-container)
containers. You can easily extend the default ``Container`` implementation with your own custom
lifetime strategies, instance providers, or nested containers to fit your needs.

The library also provides [context containers](#context-container) for cascading dependency
resolution down a nested context hierarchy.

## Table of Contents

* [Installation](#installation)
* [Basic Container Setup](#basic-container-setup)
* [Adding Dependencies to the Container](#adding-dependencies-to-the-container)
    * [Lifetime Strategies](#lifetime-strategies)
        * [Singleton](#singleton)
        * [Transient](#transient)
    * [Instance Providers](#instance-providers)
        * [Class Instantiation](#class-instantiation)
        * [Implementation Aliasing](#implementation-aliasing)
        * [Factory Method Invocation](#factory-method-invocation)
        * [Object Instance](#object-instance)
    * [Nested Containers](#nested-containers)
        * [Namespace Container](#namespace-container)
        * [Implementation Container](#implementation-container)
* [Context Container](#context-container)

## Installation

Add ``fivetwo/dependency-injection`` to the ``require`` section of your application's 
``composer.json`` file.

```json
{
    "require": {
        "fivetwo/dependency-injection": "*"
    }
}
```

## Basic Container Setup

The basic ``Container`` class contains methods for building the container and retrieving instances.

```php
use FiveTwo\DependencyInjection\Container;

$container = new Container();
$container
    // Add your main application class
    ->addSingletonClass(MyApplication::class)
    // Add the rest of your application's dependencies
    // ...
    // Fetch your main application class from the container and invoke it
    ->get(MyApplication::class)
    ->run();
```

## Building the Container

### Lifetime Strategies

A lifetime strategy determines when the container should request a fresh instance of a class from
its [instance provider](#instance-providers). There are two builtin lifetime strategies for classes:
singleton and transient.

#### Singleton

When a singleton strategy receives a request for a class for the first time, it calls the class's
instance provider, stores the result, and returns it. All subsequent requests for that class return 
that same instance for the lifetime of the container. The default ``Container`` provides 
convenience methods starting with the prefix ``addSingleton`` for adding singleton factories.

#### Transient

Each time a transient strategy receives a request for a class, it calls the class's instance 
provider and returns the result. The transient strategy does not restore the result and will 
request a new instance from the factory each time it is called. The default ``Container`` provides 
convenience methods starting with the prefix ``addTransient`` for adding transient factories.

#### Custom Strategies

Custom lifetime strategies can also be added by implementing the ``LifetimeStrategy`` interface.

### Adding Dependencies to the Container

Instance providers object instances when required by the class's lifetime strategy. The default 
``Container`` provides convenience methods for each of the built-in instance providers.

#### Class Constructor Auto-Wiring

Classes are constructed by resolving any dependencies in the class's constructor (if it exists) 
using the container and instantiating it.
The optional ``$mutator`` callback allows additional configuration of the object after it has been
instantiated.

This provision strategy is implemented by the ``ClassInstanceProvider`` class.

```php
/* Convenience methods for adding class instantiation factories. */

class Container {
    function addSingletonClass<T>(
        string<T> $className,
        callable(T $newInstance, ...):void $mutator = null
    ): static;
    
    function addTransientClass<T>(
        string<T> $className,
        callable(T $newInstance, ...):void $mutator = null
    ): static;
}

/* Examples */

// Instantiate by auto-wiring the constructor
$container->addSingletonClass(MyDependency::class);

// Instantiate by auto-wiring the constructor and inject additional optional properties
$container->addTransient(
    MyDependency::class,
    function (MyDependency $obj, Logger $logger) {
        $obj->setOptionalLogger($logger);
    }
);
```

#### Implementation Mapping

``ImplementationInstanceProvider`` maps an interface or base class to a concrete implementation. 
When an instance of the interface or base class is requested, the factory will request an 
instance of the implementation class from the container.

```php
/* Convenience methods for adding implementation factories */

class Container {
    function addSingletonImplementation<T, I implements T>(
        string<T> $className,
        string<I> $implementationClassName
    ): static;
    
    function addTransientImplementation<T, I implements T>(
        string<T> $className,
        string<I> $implementationClassName
    ): static;
}

/* Examples */

$container->addSingletonImplementation(HttpClient::class, CurlHttpClient::class);
```

#### Factory Method Invocation

The ``ClosureInstanceProvider`` requests instances of a class from a factory method provided as a 
``Closure``.

```php
/* Convenience methods for adding closure factories */

class Container {
    function addSingletonFactory<T>(
        string<T> $className,
        callable(...):(T|null) $factory
    ): static;
    
    function addTransientFactory<T>(
        string<T> $className,
        callable(...):(T|null) $factory
    ): static;
}

/* Examples */

// Wire parameters from a configuration
$container->addSingletonFactory(
    Mailer::class,
    fn (AppConfig $config) => new Mailer($config->mailerTransport)
);
    
// Provide an inline implementation for an interface
$container->addTransientFactory(
    Logger::class,
    fn (FileWriter $writer) => new class($writer) implements Logger {
        public function __construct(FileWriter $writer) { /* ... */ }
        /* ... */
    }
);
```

#### Object Instance

The ``ObjectInstanceProvider`` provides a single, pre-existing class instance.

```php
/* Convenience methods for adding object instance factories */

class Container {
    function addSingletonInstance<T>(string<T> $className, T|null $instance): static;
    function addTransientInstance<T>(string<T> $className, T|null $instance): static;
}

/* Examples */

$container->addSingletonInstance(Request::class, $currentRequest);
```

### Nested Containers

The default ``Container`` will also search nested instances of ``ContainerInterface`` in the order
they are added for instances of a dependency if no factory for a given class exists in the outer 
container.

#### Namespace Container

``NamespaceContainer`` will provide an instance of the requested class if it is in the specified
namespace. Instances are acquired from the given factory, or by auto-wiring the constructor if 
no factory is provided. The factory must take the class name as the first parameter. Additional 
parameters will be injected.

```php
/* Convenience methods for adding namespace containers */

class Container {
    function addSingletonNamespace(
        string $namespace,
        callable<T>(string<T> $className, ...):(T|null) $factory = null
    ): static;
    
    function addTransientNamespace(
        string $namespace,
        callable<T>(string<T> $className, ...):(T|null) $factory = null
    ): static;
}

/* Examples */

$container->addSingletonNamespace('MyNamespace');
$container->addTransientNamespace(
    'MyMailers',
    fn (string $className, AppConfig $config) => new $className($config->mailerTransport)
);
$container->addSingletonImplementation(MyNamespace\HttpClient::class, MyNamespace\CurlHttpClient::class);

// will use the namespace container
$app = $container->get(MyNamespace\MyApplication::class);

// will resolve HttpClient to the concrete class CurlHttpClient and then instantiate it using its
// constructor because CurlHttpClient is found in the namespace container
$client = $container->get(MyNamespace\HttpClient::class);
```

#### Implementation Container

``ImplementationContainer`` will provide an instance of the requested class if it is a subclass of
the specified interface or base class. Instances are acquired from the given factory, or by
auto-wiring the constructor if no factory is provided. The factory must take the class name as the
first parameter. Additional parameters will be injected.

```php
/* Convenience methods for adding implementation containers */

class Container {
    function addSingletonInterface<T>(
        string<T> $className,
        callable<I implements T>(string<I> $className, ...):(I|null) $factory = null
    ): static;
    
    function addTransientInterface<T>(
        string<T> $className,
        callable<I implements T>(string<I> $className, ...):(I|null) $factory = null
    ): static;
}

/* Examples */

$container
    ->addSingletonInterface(
        EntityNameProvider::class,
        fn (string $className, EntityManager $em) =>
            $em->getRepository($className::getEntityName())
    )
    ->addSingletonFactory(
        MyRepository::class,
        fn (EntityManger $em, Logger $logger) =>
            $em->getRepository(MyEntity::class)->setLogger($logger)
    );

// will use the nested implementation container's factory
$app = $container->get(UserRepository::class); 

// will use class-specific factory defined for MyRepository
$client = $container->get(MyRepository::class);
```

## Context Container

```php
use FiveTwo\DependencyInjection\Context\ContextContainerFactory;
use FiveTwo\DependencyInjection\Context\Context;

// Strings or enums can be used as identifiers for contexts. To help ease analysis and future
// refactorings, enums or string-typed constants are recommended.
enum MyContexts {
    case Default;
    case Admin;
}

$container = ContextContainerFactory::createForDefaultContainer();

// Build the default context's container
$container->context(MyContexts::Default)
    ->addSingletonClass(MyApplication::class)
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('default.json')
    );

// Build the Admin context's container
$container->context(MyContexts::Admin)
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('admin.json')
    );

$container
    // Make Default the default, fallback context
    ->push(MyContexts::Default)
    ->get(MyApplication::class)
    ->run();

// The container will search the Admin context then the Default context for each dependency in the
// following class 
#[Context(MyContexts::Admin)]
class AdminEditDefaultSettingsController {

    // The container will inherit its context search stack from the class for the following function
    public function __construct(
        // The container will use the Settings factory in the Admin context
        private readonly Settings $settings,
        
        // The container will use the Settings factory in the Default context
        #[Context(MyContexts::Default)]
        private readonly Settings $defaultSettings,
        
        // The container will not find HttpClient in Admin context, so it will use the factory in
        // the Default context
        private readonly HttpClient $httpClient
    ) {
    }
}
```
