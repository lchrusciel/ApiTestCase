<?php

declare(strict_types=1);

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase\Test\Tests\Controller;

use ApiTestCase\JsonApiTestCase;
use ApiTestCase\Test\Entity\Product;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class SampleControllerJsonTest extends JsonApiTestCase
{
    public function testGetHelloWorldResponse(): void
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world');
    }

    #[DataProvider('provideTestData')]
    public function testGetHelloWorldResponseWithDataProvider(string $method): void
    {
        $this->client->request($method, '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world');
    }

    /**
     * @return iterable<array{method: string}>
     */
    public static function provideTestData(): iterable
    {
        yield ['method' => 'GET'];
    }

    public function testGetHelloWorldResponseWithCharsetOnContentType(): void
    {
        $this->client->request('GET', '/', [], [], ['HTTP_ACCEPT' => 'application/json; charset=utf-8']);

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world');
    }

    public function testGetHelloWorldResponseWithProblemOnContentType(): void
    {
        $this->client->request('GET', '/', [], [], ['HTTP_ACCEPT' => 'application/problem+json; charset=utf-8']);

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world');
    }

    public function testGetHelloWorldResponseWithEscapedUnicode(): void
    {
        $_SERVER['ESCAPE_JSON'] = true;

        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world_escaped');
    }

    public function testGetHelloWorldIncorrectResponse(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'incorrect_hello_world');
        $this->expectException(AssertionFailedError::class);
    }

    public function testGetHelloWorldWithMatcherResponse(): void
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_matcher_world');
    }

    public function testGetHelloWorldWithWildCardResponse(): void
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_wild_card');
    }

    public function testProductIndexResponse(): void
    {
        $this->loadFixturesFromDirectory();

        $this->client->request('GET', '/products/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'product_index');
    }

    public function testCategoryIndexResponse(): void
    {
        $this->loadFixturesFromFiles(['product.yml', 'category.yml']);

        $this->client->request('GET', '/categories/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'category_index');
    }

    public function testProductShowResponse(): void
    {
        $objects = $this->loadFixturesFromDirectory();
        \Webmozart\Assert\Assert::isInstanceOf($objects['product1'], Product::class);

        $this->client->request('GET', '/products/' . $objects['product1']->getId());

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'get_product');
    }

    public function testProductCreateResponse(): void
    {
        $data =
<<<EOT
        {
            "name": "Star Wars T-Shirt",
            "price": 1000,
            "uuid": "d914ffec-5ad0-4b52-b465-46b10e2548e7"
        }
EOT;

        $this->client->request('POST', '/products/', (array) json_decode($data), [], ['CONTENT_TYPE' => 'application/json']);
        $response = $this->client->getResponse();

        $this->assertResponse($response, 'create_product', Response::HTTP_CREATED);
    }
}
