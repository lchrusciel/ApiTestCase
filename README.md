# ApiTestCase

**ApiTestCase** is an one-file library that will make your life as a Symfony2 API developer much easier. It extends basic [Symfony2](https://symfony.com/) WebTestCase with some cool features. Thanks to [*PHP-Matcher*](https://github.com/coduo/php-matcher) you can write expected json responses like a gangster(According to php-matcher README.md). [*SymfonyMockerContainer*](https://github.com/PolishSymfonyCommunity/SymfonyMockerContainer) makes it super easy to mock services. This library merge this two great concepts and extends them with few more lines, to achieve pure awesomeness.

Installation
------------

Assuming one already has Composer installed globally:

```bash
$ composer require lakion/api-test-case
```

Then one has to change slightly your Kernel logic to support SymfonyMockerContainer:

```php
// app/AppKernel.php

protected function getContainerBaseClass()
{
    if ('test' === $this->environment) {
        return '\PSS\SymfonyMockerContainer\DependencyInjection\MockerContainer';
    }

    return parent::getContainerBaseClass();
}
```

At the end a few more lines are required in a phpunit.xml file:
```xml
<php>
    <server name="KERNEL_DIR" value="/path/to/dir/with/kernel" />
    <server name="EXPECTED_RESPONSE_DIR" value="/path/to/expected/responses/" />
    <server name="MOCKED_RESPONSE_DIR" value="/path/to/mocked/responses/" />
    <server name="OPEN_ERROR_IN_BROWSER" value="true/false" />
</php>
```

Testing
-------
In order to test this library run:
```bash
$ composer install
$ bin/phpunit test/
```

Sample project
--------------
In a test directory, you can find sample Symfony2 project with minimal configuration required to use this library.
