# Five Two Dependency Injection Framework for PHP

The Five Two Dependency Injection Framework provides a highly customizable dependency injection framework for projects
running on PHP 8.1 or later. This framework focuses on facilitating sound OOP practices, refactoring, and static
analysis by emphasizing factory methods as the primary means of building complex dependencies. Thus, by design the
framework can only resolve and inject ``object`` dependencies and does not rely on configuration files for setting up
dependencies.

```php
$container = (new FiveTwo\DependencyInjection\Container())
    ->addSingletonClass(MyApplication::class)
    // Add the rest of your dependencies...
    ->get(MyApplication::class)
    ->run();
```

Out of the box, the framework provides [singleton](#singleton) and [transient](#transient)
[lifetime strategies](#lifetime-strategies) and a variety of [instance factory](#instance-strategies) strategies, in
addition to nested [namespace](#namespace-container) and [interface implementation](#implementation-container)
containers. You can easily extend the default ``Container`` implementation with your own custom lifetime strategies,
instance factory strategies, or nested containers to fit your needs.

The framework also introduces the concept of [context containers](#context-container) for cascading dependency
resolution down a nested context hierarchy.

## Table of Contents

* [Installation](#installation)
* [Basic Container Setup](#basic-container-setup)
* [Adding Dependencies to the Container](#adding-dependencies-to-the-container)
    * [Lifetime Strategies](#lifetime-strategies)
        * [Singleton](#singleton)
        * [Transient](#transient)
    * [Instance Strategies](#instance-strategies)
        * [Class Instantiation](#class-instantiation)
        * [Implementation Aliasing](#implementation-aliasing)
        * [Factory Method Invocation](#factory-method-invocation)
        * [Object Instance](#object-instance)
    * [Nested Containers](#nested-containers)
        * [Namespace Container](#namespace-container)
        * [Implementation Container](#implementation-container)
* [Context Container](#context-container)

## Installation

Add ``fivetwo/dependency-injection`` to the ``require`` section of your application's ``composer.json`` file.

```json
{
    "require": {
        "fivetwo/dependency-injection": "*"
    }
}
```

## Basic Container Setup

The basic ``Container`` class contains methods for building the container and retrieving instances. Singleton instances
persist for the lifetime of the container. Transient instances are unique to each invocation of ``Container::get()``.
Custom ``LifetimeStrategy`` implementations can also be created.

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

## Adding Dependencies to the Container

### Lifetime Strategies

There are two builtin lifetime strategies for classes: singleton and transient.

#### Singleton

When a singleton strategy receives a request for a class for the first time, it calls the class's factory, stores the
result, and returns it. All subsequent requests for that class return that same instance for the lifetime of the
container. The default ``Container`` provides convenience methods starting with the prefix ``addSingleton`` for adding
singleton factories.

#### Transient

Each time a transient strategy receives a request for a class, it calls the class's factory and returns the result. The
transient strategy does not restore the result and will request a new instance from the factory each time it is called.
The default ``Container`` provides convenience methods starting with the prefix ``addTransient`` for adding transient
factories.

#### Custom Strategies

Custom lifetime strategies can also be added by implementing the ``LifetimeStrategy`` interface.

### Instance Strategies

Instance factories handle the provision of instances to the lifetime strategy. The default ``Container`` provides
convenience methods for each of the built-in instance factories.

#### Class Instantiation

The ``ClassInstanceFactory`` creates instances by invoking the class's constructor (if it exists) with its parameters
injected from the container. The default ``Container`` provides the convenience methods ``addSingletonClass`` and
``addTransientClass`` for adding class factories.

```php
$container
    ->addSingletonClass(MyDependency::class)
    ->addTransientClass(CurlHttpClient::class);
```

The optional ``$mutator`` callback allows additional configuration of the object after it has been instantiated.

```php
$container->addSingletonClass(
    MyDependency::class,
    function (MyDependency $obj, Logger $logger) {
        $obj->setLogger($logger);
    }
);
```

#### Implementation Aliasing

The ``ImplementationInstanceFactory`` aliases an interface or base class to an implementation class. When an instance of
the interface or base class is requested, the factory will request an instance of the implementation class from the
container. The default ``Container`` provides the convenience methods ``addSingletonImplementation`` and
``addTransientImplementation`` for adding interface aliases.

```php
$container
    ->addSingletonImplementation(Logger::class, FileLogger::class)
    ->addTransientClass(HttpClient::class, CurlHttpClient::class);
```

#### Factory Method Invocation

The ``ClosureInstanceFactory`` requests instances of a class from a factory method provided as a ``Closure``. The
default ``Container`` provides the convenience methods ``addSingletonFactory`` and``addTransientFactory`` for adding
factory methods.

```php
$container
    ->addSingletonFactory(
        DbConnector::class,
        fn (AppConfig $config) => new DbConnector(
            $config->db->hostname,
            $config->db->username,
            $config->db->password,
            $config->db->database
        )
    )
    ->addTransientFactory(Mailer::class, fn () => new Mailer('sendmail'));
```

#### Object Instance

The ``ObjectInstanceFactory`` provides a single, pre-existing class instance. The default ``Container`` provides the
convenience method ``addSingletonInstance`` for adding object instances.

```php
$container->addSingletonInstance(Request::class, $currentRequest);
```

### Nested Containers

The default ``Container`` will also search nested instances of ``ContainerInterface`` in the order they are added for
instances of a dependency if no factory for a given class exists in the outer container.

#### Namespace Container

``NamespaceContainer`` will provide an instance of the requested class if it is in the specified namespace. Instances
are acquired from the given factory, or by auto-wiring the constructor if no factory is provided. The factory must take
the class name as the first parameter. Additional parameters will be injected. The default ``Container`` provides the
convenience methods ``addSingletonNamespace`` and ``addTransientNamespace`` for adding namespaces.

```php
$container
    ->addSingletonNamespace('MyNamespace')
    ->addTransientNamespace(
        'MyMailers',
        fn (string $className, AppConfig $config) => new $className($config->transport)
    )
    ->addSingletonImplementation(MyNamespace\HttpClient::class, MyNamespace\CurlHttpClient::class);

$app = $container->get(MyNamespace\MyApplication::class); // uses the nested namespace container
$client = $container->get(MyNamespace\HttpClient::class); // uses the implementation factory
```

#### Implementation Container

``ImplementationContainer`` will provide an instance of the requested class if it is a subclass of the specified
interface or base class. Instances are acquired from the given factory, or by auto-wiring the constructor if no factory
is provided. The factory must take the class name as the first parameter. Additional parameters will be injected. The
default ``Container`` provides the convenience methods ``addSingletonInterface`` and ``addTransientInterface`` for
adding interface implementation containers.

```php
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

$container
    ->addSingletonInterface(
        EntityNameProvider::class,
        fn (string $className, EntityManager $em) => $em->getRepository($className::getEntityName())
    )
    ->addTransientInterface(Logger::class)
    ->addSingletonFactory(
        MyRepository::class,
        fn (EntityManger $em, Logger $logger) => $em->getRepository(MyEntity::class)->setLogger($logger)
    );

$app = $container->get(UserRepository::class); // uses the nested implementation container
$client = $container->get(MyRepository::class); // uses the class-specific factory

class UserRepository extends EntityRepository implements EntityNameProvider
{
    public static function getName(): string {
        return User::class;
    }
}

class MyRepository extends EntityRepository implements EntityNameProvider
{
    // ...
}
```

## Context Container

```php
use FiveTwo\DependencyInjection\Context\ContextContainerFactory;
use FiveTwo\DependencyInjection\Context\Context;

// Strings or enums can be used as identifiers for contexts. To help ease analysis and future refactorings, enums or
// string-typed constants are recommended.
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

// The container will search the Admin context then the Default context for each dependency in the following class 
#[Context(MyContexts::Admin)]
class AdminEditDefaultSettingsController {

    // The container will inherit its context search stack from the class for the following function
    public function __construct(
        // The container will use the Settings factory in the Admin context
        private readonly Settings $settings,
        
        // The container will use the Settings factory in the Default context
        #[Context(MyContexts::Default)]
        private readonly Settings $defaultSettings,
        
        // The container will not find HttpClient in Admin context, so it will use the factory in the Default context
        private readonly HttpClient $httpClient
    ) {
    }
}
```
