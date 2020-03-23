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

use ApiTestCase\XmlApiTestCase;
use PHPUnit\Framework\AssertionFailedError;

class SampleControllerXmlTest extends XmlApiTestCase
{
    public function testGetHelloWorldResponse(): void
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world');
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

    public function testCategoriesIndexResponse(): void
    {
        $this->loadFixturesFromFiles(['product.yml', 'category.yml']);

        $this->client->request('GET', '/categories/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'category_index');
    }

    public function testProductShowResponse(): void
    {
        $objects = $this->loadFixturesFromDirectory();

        $this->client->request('GET', '/products/' . $objects['product1']->getId());

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'get_product');
    }
}
