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

namespace ApiTestCase;

use Symfony\Component\HttpFoundation\Response;

abstract class JsonApiTestCase extends ApiTestCase
{
    /**
     * @before
     */
    public function setUpClient()
    {
        $this->client = static::createClient([], ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMatcher()
    {
        return $this->matcherFactory->buildJsonMatcher();
    }

    /**
     * Asserts that response has JSON content.
     * If filename is set, asserts that response content matches the one in given file.
     * If statusCode is set, asserts that response has given status code.
     *
     * @param Response $response
     * @param string $filename
     * @param int $statusCode (optional)
     *
     * @throws \Exception
     */
    protected function assertResponse(Response $response, string $filename, int $statusCode = 200)
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
    protected function assertJsonHeader(Response $response)
    {
        parent::assertHeader($response, 'application');
        parent::assertHeader($response, 'json');
    }

    /**
     * Asserts that response has JSON content matching the one given in file.
     *
     * @param Response $response
     * @param string $filename
     *
     * @throws \Exception
     */
    protected function assertJsonResponseContent(Response $response, string $filename)
    {
        parent::assertResponseContent($this->prettifyJson($response->getContent()), $filename, 'json');
    }

    /**
     * @param mixed $content
     *
     * @return string
     */
    protected function prettifyJson($content)
    {
        $jsonFlags = JSON_PRETTY_PRINT;
        if (!isset($_SERVER['ESCAPE_JSON']) || true !== $_SERVER['ESCAPE_JSON']) {
            $jsonFlags = $jsonFlags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        }

        /** @var string $encodedContent */
        $encodedContent = json_encode(json_decode($content, true), $jsonFlags);
        return $encodedContent;
    }
}
