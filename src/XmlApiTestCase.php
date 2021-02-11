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

use Coduo\PHPMatcher\Backtrace\VoidBacktrace;
use Coduo\PHPMatcher\Matcher;
use Symfony\Component\HttpFoundation\Response;

abstract class XmlApiTestCase extends ApiTestCase
{
    /**
     * @before
     */
    public function setUpClient(): void
    {
        $this->client = static::createClient([], ['HTTP_ACCEPT' => 'application/xml']);
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMatcher(): Matcher
    {
        return $this->matcherFactory->createMatcher(new VoidBacktrace());
    }

    /**
     * @throws \Exception
     */
    protected function assertResponse(Response $response, string $filename, int $statusCode = 200): void
    {
        if (isset($_SERVER['OPEN_ERROR_IN_BROWSER']) && true === $_SERVER['OPEN_ERROR_IN_BROWSER']) {
            $this->showErrorInBrowserIfOccurred($response);
        }

        $this->assertResponseCode($response, $statusCode);
        $this->assertXmlHeader($response);
        $this->assertXmlResponseContent($response, $filename);
    }

    protected function assertXmlHeader(Response $response): void
    {
        parent::assertHeader($response, 'application/xml');
    }

    /**
     * @throws \Exception
     */
    protected function assertXmlResponseContent(Response $actualResponse, string $filename): void
    {
        parent::assertResponseContent($this->prettifyXml($actualResponse->getContent() ?: ''), $filename, 'xml');
    }

    protected function prettifyXml(string $actualResponse): string
    {
        $domXmlDocument = new \DOMDocument('1.0');
        $domXmlDocument->preserveWhiteSpace = false;
        $domXmlDocument->formatOutput = true;
        $domXmlDocument->loadXML(str_replace("\n", '', $actualResponse));

        return $domXmlDocument->saveXML();
    }
}
