<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Lakion
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
abstract class ApiJsonTestCase extends ApiTestCase
{
    public function setUp()
    {
        $this->client = static::createClient(array(), array('HTTP_ACCEPT' => MediaTypes::JSON));
    }

    /**
     * Asserts that response has JSON content.
     * If filename is set, asserts that response content matches the one in given file.
     * If statusCode is set, asserts that response has given status code.
     *
     * @param Response $response
     * @param string|null $filename
     * @param int|null $statusCode
     *
     * @throws \Exception
     */
    protected function assertResponse(Response $response, $filename, $statusCode = 200)
    {
        if (isset($_SERVER['OPEN_ERROR_IN_BROWSER']) && true === $_SERVER['OPEN_ERROR_IN_BROWSER']) {
            $this->showErrorInBrowserIfOccurred($response);
        }

        $this->assertResponseCode($response, $statusCode);
        $this->assertJsonHeader($response);
        $this->assertJsonResponseContent($response, $filename);
    }

    /**
     * @param Response $response
     */
    private function assertJsonHeader(Response $response)
    {
        parent::assertHeader($response, MediaTypes::JSON);
    }

    /**
     * Asserts that response has JSON content matching the one given in file.
     *
     * @param Response $response
     * @param string $filename
     *
     * @throws \Exception
     */
    private function assertJsonResponseContent(Response $response, $filename)
    {
        parent::assertResponseContent($response, $filename, 'json');
    }
}
