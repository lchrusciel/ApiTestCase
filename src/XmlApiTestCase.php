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
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Paweł Jędrzęjewski <pawel.jedrzejewski@lakion.com>
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
abstract class XmlApiTestCase extends ApiTestCase
{
    /**
     * @before
     */
    public function setUpClient()
    {
        $this->client = static::createClient(array(), array('HTTP_ACCEPT' => static::XML));
    }

    /**
     * @param Response $response
     * @param string   $filename
     * @param int      $statusCode
     *
     * @throws \RuntimeException
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
    private function assertXmlHeader(Response $response)
    {
        parent::assertHeader($response, static::XML);
    }

    /**
     * @param Response $actualResponse
     * @param string   $filename
     */
    private function assertXmlResponseContent(Response $actualResponse, $filename)
    {
        parent::assertResponseContent($this->prettifyXml($actualResponse->getContent()), $filename, 'xml');
    }

    /**
     * @param $actualResponse
     *
     * @return string
     */
    private function prettifyXml($actualResponse)
    {
        $domXmlDocument = new \DOMDocument('1.0');
        $domXmlDocument->preserveWhiteSpace = false;
        $domXmlDocument->formatOutput = true;
        $domXmlDocument->loadXML(str_replace("\n", '', $actualResponse));

        return $domXmlDocument->saveXML();
    }
}
