# Dependency Injection Library for PHP

The Five Two Dependency Injection library provides a highly customizable
dependency injection framework for projects running on PHP 8.1 or later. This
library focuses on facilitating sound object-oriented practices, testing, 
refactoring, and static analysis by emphasizing factory methods and class
constructors as the primary means of building complex dependencies. By design
the framework can only resolve and inject `object`-typed dependencies and does
not rely on configuration files for the container specification.

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

The library also provides [context containers](#context-container) for cascading
dependency resolution down a nested context hierarchy.

## Table of Contents

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Instance Lifetime](#instance-lifetime)
    - [Singleton](#singleton)
    - [Transient](#transient)
- [Adding Dependencies to the Container](#adding-dependencies-to-the-container)
    - [Class Constructor Autowiring](#class-constructor-autowiring)
    - [Interface-Implementation Mapping](#interface-implementation-mapping)
    - [Factory Method Invocation](#factory-method-invocation)
    - [Object Instance Provision](#object-instance-provision)
- [Nested Containers](#nested-containers)
    - [Namespace Container](#namespace-container)
    - [Interface Container](#interface-container)
- [Customizing the Container](#customizing-the-container)
  - [Custom Lifetime Strategies](#custom-lifetime-strategies)
  - [Custom Instance Providers](#custom-instance-providers)
  - [Custom Nested Containers](#custom-nested-containers)
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

If your application has other entry points (e.g. controllers), it might be 
useful to inject the controller into the part of your application that invokes
those entry points (e.g. a router).

```php
$container->addSingletonInstance(Container::class, $container);

class MyRouter
{
    public function __construct(private readonly Container $container)
    {
    }
    
    public function routeRequest(string $method, string $path): void
    {
        $controllerClassName = $this->getControllerClassName($path);
        $controller = $this->container->get($controllerClassName);
        $controller->handleRequest($method);
    }
}
```

### A note on the service locators pattern

Please note that while `Container` is functionally equivalent to a service 
locator, the service locator pattern should usually be avoided because it makes
testing and refactoring more difficult, and generally makes it more difficult to
reason about your application.

Only your application's entry points should invoke the container's `get()`
method. If you know the specific object type required before runtime, you should
rely on the container's automatic dependency injection capabilities instead of
directly invoking the container's `get()` method.

```php
/* Do NOT do this! */

class MyApiCaller
{
    public function __construct(private readonly Container $container) {
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
`MyClass`'s actual dependencies much clearer and thereby make testing and 
refactoring it far easier. 

```php
/* Do this instead! */

class MyApiCaller
{
    public function __construct(
        private readonly HttpClient $httpClient
    ) { }

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
and made available to legacy code as an intermediate step. Once you are
eventually able to refactor all uses of the singleton container to proper
dependency injection, the singleton container can be removed.

```php
/* TODO: Refactor this! */
public function myBloatedFunction(...$args)
{
    // ...
    $httpClient = getAppContainer()->get(HttpClient::class);
    $result = $httpClient->get('https://www.example.com/api');
    // ...
}

/* TODO: Refactor this! */
public function myLegacyFunction(...$args)
{
    // ...
    myBloatedFunction($args[1], 'magic string', 42, $args[17]);
    // ...
}

/* TODO: Eliminate all uses of this and remove! */
function getAppContainer(): Container
{
    static $container;
    return $container ??= new Container();
}
```
 

### Instance Lifetime

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

### Adding Dependencies to the Container

There are a number of built-in ways to specify how new instances should be 
created.

 - [Class Constructor Autowiring](#class-constructor-autowiring)
 - [Interface-Implementation Mapping](#interface-implementation-mapping)
 - [Factory Method Invocation](#factory-method-invocation)
 - [Object Instance Provision](#object-instance-provision)

If needed, can also specify your own custom
[instance providers](#custom-instance-providers).

For more information on the syntax used in this document for API information,
please refer to the [API Syntax](#api-syntax) appendix.

#### Class Constructor Autowiring

The container will construct classes by calling the class's constructor,
automatically resolving any dependencies in the constructor's parameter list.

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

##### Examples

###### Basic usage

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

##### Examples

###### Basic usage

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
autowiring its constructor.

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

##### Examples

###### Basic usage

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

###### Inline class implementations

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

#### Object Instance Provision

The container will provide a pre-constructed instance of a class.

```php
class Container {
    function addSingletonInstance<TClass>(
        string<TClass> $className,
        TClass|null $instance
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

### Nested Containers

If the container cannot find a way to provide an instance of a specific class,
it will next check to see if there are any nested containers that can provide
the value. Two built-in nested container implementations are provided: namespace
and implementation. You can also add custom containers that implement
`ContainerInterface` using the `addContainer()` method. Nested containers are
searched sequentially in the order they are added.

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

### Customizing the Container

#### Custom Lifetime Strategies

Implement `LifetimeStrategy` and optionally extend `Container` with convenience
methods for your new lifetime strategy.

#### Custom Instance Providers

Implement `InstanceProvider` and add it to your container using one of the basic
add methods. You can also extend `Container` to add convenience methods for
using your new instance provider.

```php
class Container {
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
callable LifetimeStrategyFactory<T>(string<T> $className): LifetimeStrategy<T>;

class Container {
    public function addContainer(
        ContainerInterface $container,
        callable<T>(string<T> $className): LifetimeStrategy<T> 
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
syntax inspired by TypeScript and C#.

```php
function exampleFunction<TClass>(string<TClass> $className): TClass;
```

The above `exampleFunction` takes a generic type `TClass` (in this document, all
generics are implicitly `object` types). The function takes a `string`,
`$className`, which contains the fully qualified name of the class (i.e.,
`TClass::class`). The function will return a value of type `TClass`.

```php
callable ExampleCallbackType<TClass, TSubclass of TClass>(
    string<TClass> $className,
    string<TSubclass> $subclassName
): void;

function anotherFunction<TClass, TSubclass of TClass>(
    string<TClass> $className,
    ExampleCallbackType<TClass, TSubclass> $callback
): void;
```

The above `callable` declaration is analogous to the `delegate` keyword in C#,
in this case defining a custom `callable` type, `ExampleCallbackType`. The
template takes two classes: `TClass` and another class `TSubclass` which must
be of type `TClass`. The custom type is then taken as a parameter in
`anotherFunction`.
