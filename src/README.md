# Installation

```json
{
    "require": {
        "fivetwo/dependency-injection": "*"
    }
}
```

# Usage

```php
use FiveTwo\DependencyInjection\Container;

$container = new Container();
$container
    // wire all your dependencies
    ->addSingletonClass(MyApplication::class)
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)
    // fetch an instance of your application service and invoke it
    ->get(MyApplication::class)
    ->run();
```

# Context Container

```php
use FiveTwo\DependencyInjection\Context\ContextContainerFactory;
use FiveTwo\DependencyInjection\Context\Context;

$container = ContextContainerFactory::createForDefaultContainer();

$container->context('default')
    ->addSingletonClass(MyApplication::class)
    ->addTransientImplementation(HttpClient::class, CurlHttpClient::class)
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('default.json')
    );

$container->context('admin')
    ->addSingletonFactory(
        Settings::class,
        fn () => JsonSettings::fromFile('admin.json')
    );

$container->get(MyApplication::class)
    ->run($_GET['page'] ?? 'home');

#[Context('admin')]
class AdminPageController {
    public function __construct(
        private readonly Settings $settings,
        #[Context('default')]
        private readonly Settings $defaultSettings,
        private readonly HttpClient $httpClient
    ) {
    }
}
```
