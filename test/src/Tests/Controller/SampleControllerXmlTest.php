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

    public function testGetResponseFromMockedService()
    {
        $this->client->getContainer()->mock('app.service', 'Lakion\ApiTestCase\Test\Service\DummyService')
            ->shouldReceive('getOutsideApiResponse')
            ->once()
            ->andReturn($this->getJsonResponseFixture('ambitious_action_mock'));

        $this->client->request('GET', '/service/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'ambitious_action_response');
    }

    public function testProductIndexResponse()
    {
        $this->loadFixturesFromFile('product.yml');

        $this->client->request('GET', '/products/');

        $response = $this->client->getResponse();

        $this->assertResponse($response, 'get_products');
    }
}
