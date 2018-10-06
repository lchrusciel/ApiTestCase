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

abstract class XmlApiTestCase extends ApiTestCase
{
    /**
     * @before
     */
    public function setUpClient()
    {
        $this->client = static::createClient(array(), array('HTTP_ACCEPT' => 'application/xml'));
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMatcher()
    {
        return MatcherFactory::buildXmlMatcher();
    }

    /**
     * @param Response $response
     * @param string $filename
     * @param int $statusCode
     *
     * @throws \Exception
     */
    protected function assertResponse(Response $response, $filename, $statusCode = 200)
    {
        if (isset($_SERVER['OPEN_ERROR_IN_BROWSER']) && true === $_SERVER['OPEN_ERROR_IN_BROWSER']) {
            $this->showErrorInBrowserIfOccurred($response);
        }

        $this->assertResponseCode($response, $statusCode);
        $this->assertXmlHeader($response);
        $this->assertXmlResponseContent($response, $filename);
    }

    /**
     * @param Response $response
     */
    protected function assertXmlHeader(Response $response)
    {
        parent::assertHeader($response, 'application/xml');
    }

    /**
     * @param Response $actualResponse
     * @param $filename
     *
     * @throws \Exception
     */
    protected function assertXmlResponseContent(Response $actualResponse, $filename)
    {
        parent::assertResponseContent($this->prettifyXml($actualResponse->getContent()), $filename, 'xml');
    }

    /**
     * @param $actualResponse
     *
     * @return string
     */
    protected function prettifyXml($actualResponse)
    {
        $domXmlDocument = new \DOMDocument('1.0');
        $domXmlDocument->preserveWhiteSpace = false;
        $domXmlDocument->formatOutput = true;
        $domXmlDocument->loadXML(str_replace("\n", "", $actualResponse));

        return $domXmlDocument->saveXML();
    }
}
