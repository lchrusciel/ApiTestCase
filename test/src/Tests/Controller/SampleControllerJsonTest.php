<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Lakion
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase\Test\Tests\Controller;

use Lakion\ApiTestCase\JsonApiTestCase;
use PHPUnit_Framework_AssertionFailedError;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 */
class SampleControllerJsonTest extends JsonApiTestCase
{
    public function testGetHelloWorldResponse()
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_world');
    }

    /**
     * @expectedException PHPUnit_Framework_AssertionFailedError
     */
    public function testGetHelloWorldIncorrectResponse()
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'incorrect_hello_world');
    }

    public function testGetHelloWorldWithMatcherResponse()
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_matcher_world');
    }

    public function testGetHelloWorldWithWildCardResponse()
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'hello_wild_card');
    }

    public function testGetProductInventoryFromThirdPartyApi()
    {
        $this->client->getContainer()->mock('app.third_party_api_client', 'Lakion\ApiTestCase\Test\Service\ThirdPartyApiClient')
            ->shouldReceive('getInventory')
            ->once()
            ->andReturn($this->getJsonResponseFixture('third_party_api_inventory'))
        ;

        $this->client->request('GET', '/use-third-party-api/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'use_third_party_api');
    }

    public function testProductIndexResponse()
    {
        $this->loadFixturesFromDirectory();

        $this->client->request('GET', '/products/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'product_index');
    }

    public function testProductShowResponse()
    {
        $objects = $this->loadFixturesFromDirectory();

        $this->client->request('GET', '/products/' . $objects['product1']->getId());

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'get_product');
    }

    public function testProductCreateResponse()
    {
        $data =
<<<EOT
        {
            "name": "Star Wars T-Shirt",
            "price": 1000
        }
EOT;

        $this->client->request('POST', '/products/', (array) json_decode($data), [], ['CONTENT_TYPE' => 'application/json']);
        $response = $this->client->getResponse();

        $this->assertResponse($response, 'create_product', Response::HTTP_CREATED);
    }
}
