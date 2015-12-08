# ApiTestCase
[![Build Status](https://travis-ci.org/Lakion/ApiTestCase.svg?branch=master)](https://travis-ci.org/Lakion/ApiTestCase)

**ApiTestCase** is a one-file library that will make your life as a Symfony2 API developer much easier. It extends basic [Symfony2](https://symfony.com/) WebTestCase with some cool features. Thanks to [PHP-Matcher](https://github.com/coduo/php-matcher) you can write expected json responses like a gangster. [SymfonyMockerContainer](https://github.com/PolishSymfonyCommunity/SymfonyMockerContainer) makes it super easy to mock services. This library merges these two great concepts and extends them with a few more lines, to achieve pure awesomeness.

Installation
------------

Assuming you already have Composer installed globally:

```bash
$ composer require --dev lakion/api-test-case
```

Then you have to slightly change your Kernel logic to support SymfonyMockerContainer:

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
And it's done! ApiTestCase is working with the default configuration.

Configuration Reference
-----------------------

To customize your test suite configuration you can add a few more options to phpunit.xml:

```xml
<php>
    <server name="KERNEL_DIR" value="/path/to/dir/with/kernel" />
    <server name="KERNEL_CLASS_PATH" value="/path/to/kernel/class" />
    <server name="EXPECTED_RESPONSE_DIR" value="/path/to/expected/responses/" />
    <server name="MOCKED_RESPONSE_DIR" value="/path/to/mocked/responses/" />
    <server name="FIXTURES_DIR" value="/path/to/DataFixtures/ORM/" />
    <server name="OPEN_ERROR_IN_BROWSER" value="true/false" />
    <server name="OPEN_BROWSER_COMMAND" value="open %s" />
    <server name="IS_DOCTRINE_ORM_SUPPORTED" value="true/false" />
</php>
```
 * `KERNEL_DIR` variable contains a path to kernel of your project. If not set, WebTestCase will look for AppKernel in the folder where you have your phpunit.xml file.
 * `KERNEL_CLASS_PATH` allows you to specify exactly which class in which folder should be used in order to setup the Kernel. 
 * `EXPECTED_RESPONSE_DIR` and `MOCKED_RESPONSE_DIR` variables contain paths to folders with expected and mocked responses. `EXPECTED_RESPONSE_DIR` is used when API result is compared with existing json file. `MOCKED_RESPONSE_DIR` should contains files with mocked responses from outside API's. Both variable can have same value but we recommend to keep it separated. If these values aren't set, ApiTestCase will try to guess location of responses. It will try to look for the responses in a following folders '../Responses/Expected' and '../Responses/Mocked' relatively located to your controller test class.
 * `FIXTURES_DIR` variable contains a path to folder with your data fixtures. By default if this variable isn't set it will search for `../DataFixtures/ORM/` relatively located to your test class . ApiTestCase throws RunTimeException if folder doesn't exist or there won't be any files to load.
 * `OPEN_ERROR_IN_BROWSER` is a flag which turns on displaying error in a browser window. The default value is false.
 * `OPEN_BROWSER_COMMAND` is a command which will be used to open browser with an exception.
 * `IS_DOCTRINE_ORM_SUPPORTED` is a flag which turns on doctrine support includes handy data fixtures loader and database purger.
 
Usage
-----

In api test case we provide by default two separate cases the JsonApiTestCase and the XmlApiTestCase there is only few difference in data assertion and content type of client.   

Json example
============

The most basic usage can be achieved with the following workflow:

1. Start from defining your response for a certain request.
2. Put it in a JSON file (It should be put in src/YourBundle/Tests/Responses/Expected/hello_world.json for the example below).
3. Write a test case that sends the request and expects the response from defined in point 1. and contained in file from point 2.
4. Make it red.
5. Make it green.

 
```php
namespace YourBundle\Tests\Controller\HelloWorldTest;

use Lakion\ApiTestCase\JsonApiTestCase;

class HelloWorldTest extends JsonApiTestCase
{
    public function testGetHelloWorldResponse()
    {
      $this->client->request('GET', '/');

      $response = $this->client->getResponse();

      $this->assertJsonResponse($response, 'hello_world');
    }

    //...
}
```
```json
{
    "message": "Hello ApiTestCase World!"
}
```
If the message will match, console will present simple message:
```bash
OK (1 tests, 2 assertions)
```
Otherwise it will present diff of received messages:
```bash
"Hello ApiTestCase World" does not match "Hello ApiTestCase World!".
@@ -1,4 +1,3 @@
 {
-    "message": "Hello ApiTestCase World!"
+    "message": "Hello ApiTestCase World"
 }
-
```
Firstly, function `assertJsonResponse` will check the response code (200 is a default response code), then it will check if header of response contains `application/json` content type. At the end it will check if endpoint response matches the expectation. `assertResponseCode` can be also called from your controller test class, for example to check 204 no-content response.
But sometimes you can't predict some values in an array. You don't have to forecast the future, because [PHP-Matcher](https://github.com/coduo/php-matcher) will come with a helping hand. These are just a few examples of available patterns:
* ``@string@``
* ``@integer@``
* ``@boolean@``
* ``@array@``

Check for more on [PHP-Matcher](https://github.com/coduo/php-matcher) project page. 
With these patterns your expected response will look like this
```json
{
    "message": @string@
}
```
And any string under key `message` will match the pattern. More complicated expected response could look like this:
```json
[
    {
        "id": @integer@,
        "name": "Star-Wars T-shirt",
        "sku": "SWTS",
        "price": 5500,
        "sizes": @array@
    },
    {
        "id": @integer@,
        "name": "Han Solo Mug",
        "sku": "HSM",
        "price": 500,
        "sizes": @array@
    }
]
```
And will match the following list of products:
```php
array(
    array(
        'id' => 1,
        'name' => 'Star-Wars T-shirt',
        'sku' => 'SWTS',
        'price' => 5500,
        'sizes' => array('S', 'M', 'L'),
    ),
    array(
        'id' => 2,
        'name' => 'Han Solo Mug',
        'sku' => 'HSM',
        'price' => 500,
        'sizes' => array('S', 'L'),
    ),
)
```

It is also a really common case to communicate with some external API. But in test environment we want to be sure what we will receive from it. To check behaviour of our app with different responses from external API we can use [SymfonyMockerContainer](https://github.com/PolishSymfonyCommunity/SymfonyMockerContainer). This library allows to mock service response, and asserts number of calls. 
```php
//HelloWorldTest
  public function testGetResponseFromMockedService()
  {
      $this->client->getContainer()->mock('app.service', 'Lakion\ApiTestCase\Test\Service\DummyService')
          ->shouldReceive('getOutsideApiResponse')
          ->once()
          ->andReturn(array('WithMessage'));
      //...
```
From this moment, first `getOutsideApiResponse` call will receive `array('WithMessage')` and any further call will cause an exception. To make maintaining of external API responses as easy as possible ApiTestCase provides a method to load data directly from json file `$this->getJsonResponseFixture('mocked_response')`. `mocked_response.json` file should be placed in a src/YourBundle/Tests/Responses/Mocked/ folder, or any other defined in phpunit.xml file.

Test with database fixtures
===========================
Api test case is integrated with ``nelmio/alice``. Thanks to this you can easily load your fixtures when you need them. You have to define your fixtures and place it in proper directory.

Here is some example how to define your fixtures and use case. For more information how to define your fixtures check [Alice documentation](https://github.com/nelmio/alice). 

Let's start with defining resource.

```php
    class Product
    {
        private $id;
        private $name;
        private $price;
    
        // Proper setters and getters.
    }
```

Now we are almost ready to go, few things we need to do. First one is defining mapping and second one is preparing your fixture.

```yml
    Lakion\ApiTestCase\Test\Entity\Product:
        type: entity
        table: test_product
        id:
            id:
                type: integer
                id: true
                generator:
                    strategy: AUTO
        fields:
            name:
                type: string
            price:
                type: integer
```

```yml
    Lakion\ApiTestCase\Test\Entity\Product:
        product1:
            name: 'Phone'
            price: 200
        product2:
            name: 'Book'
            price: 15
        product3:
            name: 'Mug'
            price: 5
```

Ok so we got our data, now let's proceed with our testing case.

```php
//ProductControllerTest

  public function testIndexAction()
  {
      // This method require subpath to locate specific fixture file in your DataFixtures/ORM directory.
      $this->loadFixturesFromFile('product.yml');  
      
      // There is another method that allows you to load fixtures from directory.
      $this->loadFixturesFromDirectory();
      
  // Ok you are ready to test responses.
  
```


Sample project
--------------
In the test directory, you can find sample Symfony2 project with minimal configuration required to use this library.

Testing
-------
In order to test this library run:
```bash
$ composer install
$ bin/phpunit test/
```

Bug tracking
------------

This bundle uses [GitHub issues](https://github.com/Lakion/ApiTestCase/issues).
If you have found bug, please create an issue.

Versioning
----------

Releases will be numbered with the format `major.minor.patch`.

And constructed with the following guidelines.

* Breaking backwards compatibility bumps the major.
* New additions without breaking backwards compatibility bumps the minor.
* Bug fixes and misc changes bump the patch.

For more information on SemVer, please visit [semver.org website](http://semver.org/).

MIT License
-----------

License can be found [here](https://github.com/Lakion/ApiTestCase/blob/master/LICENSE).

Authors
-------

The bundle was originally created by:

* Łukasz Chruściel <lukasz.chrusciel@lakion.com>, 
* Michał Marcinkowski <michal.marcinkowski@lakion.com>
* Paweł Jędrzejewski <pawel.jedrzejewski@lakion.com>

See the list of [contributors](https://github.com/Lakion/ApiTestCase/graphs/contributors).
