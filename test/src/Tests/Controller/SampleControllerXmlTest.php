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

use Lakion\ApiTestCase\XmlApiTestCase;
use PHPUnit_Framework_AssertionFailedError;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
class SampleControllerXmlTest extends XmlApiTestCase
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
}
