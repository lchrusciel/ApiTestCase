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

use Coduo\PHPMatcher\Factory\SimpleFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Paweł Jędrzęjewski <pawel.jedrzejewski@lakion.com>
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 */
abstract class ApiTestCase extends WebTestCase
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;

    public function setUp()
    {
        $this->client = static::createClient(array(), array('HTTP_Accept' => 'application/json'));
    }

    public function tearDown()
    {
        foreach ($this->client->getContainer()->getMockedServices() as $id => $service) {
            $this->client->getContainer()->unmock($id);
        }

        \Mockery::close();
        $this->client = null;

        parent::tearDown();
    }

    /**
     * Gets service from DIC.
     *
     * @param string $id
     *
     * @return object
     */
    protected function get($id)
    {
        return $this->client->getContainer()->get($id);
    }

    /**
     * @param Response $response
     * @param int $statusCode
     */
    protected function assertResponseCode(Response $response, $statusCode)
    {
        $this->assertEquals($statusCode, $response->getStatusCode());
    }

    /**
     * Asserts that response has JSON content.
     * If filename is set, asserts that response content matches the one in given file.
     * If statusCode is set, asserts that response has given status code.
     *
     * @param Response $response
     * @param string|null $filename
     * @param string|null $statusCode
     */
    protected function assertJsonResponse(Response $response, $filename, $statusCode = 200)
    {
        if (isset($_SERVER['OPEN_ERROR_IN_BROWSER']) && true === $_SERVER['OPEN_ERROR_IN_BROWSER']) {
            $this->showErrorInBrowserIfOccurred($response);
        }

        $this->assertResponseCode($response, $statusCode);
        $this->assertJsonHeader($response);
        $this->assertJsonResponseContent($response, $filename);
    }

    /**
     * Provides array from decoded json file. Requires MOCKED_RESPONSE_DIR defined variable to work properly.
     *
     * @param string $filename
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getJsonResponseFixture($filename)
    {
        $responseSource = $this->getMockedResponsesFolder();

        return json_decode(file_get_contents(sprintf(
            '%s/%s.json',
            $responseSource,
            $filename
        )), true);
    }

    /**
     * @param Response $response
     *
     * @throws \Exception
     */
    private function showErrorInBrowserIfOccurred(Response $response)
    {
        if (!$response->isSuccessful()) {
            $openCommand = (isset($_SERVER['OPEN_BROWSER_COMMAND'])) ? $_SERVER['OPEN_BROWSER_COMMAND'] : 'open %s';

            $filename = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid() . '.html';
            file_put_contents($filename, $response->getContent());
            system(sprintf($openCommand, escapeshellarg($filename)));

            throw new \Exception('Internal server error.');
        }
    }

    /**
     * @param Response $response
     * @param int $statusCode
     */
    private function assertJsonHeader($response)
    {
        $this->assertTrue(
            $response->headers->contains('Content-Type', 'application/json'),
            $response->headers
        );
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
        $responseSource = $this->getExpectedResponsesFolder();

        $expectedResponse = file_get_contents(sprintf(
            '%s/%s.json',
            $responseSource,
            $filename
        ));
        $actualResponse = $response->getContent();

        $actualResponse = json_encode(json_decode($actualResponse), JSON_PRETTY_PRINT);

        $factory = new SimpleFactory();
        $matcher = $factory->createMatcher();

        $result = $matcher->match($actualResponse, $expectedResponse);

        if (!$result) {
            $difference = $matcher->getError();
            $difference = $difference . PHP_EOL;

            $expectedResponse = explode(PHP_EOL, (string)$expectedResponse);
            $actualResponse = explode(PHP_EOL, (string)$actualResponse);

            $diff = new \Diff($expectedResponse, $actualResponse, array());

            $renderer = new \Diff_Renderer_Text_Unified();
            $difference = $difference . $diff->render($renderer);
            $this->fail($difference);
        }
    }

    /**
     * @return string
     */
    private function getRootDir()
    {
        return $this->get('kernel')->getRootDir();
    }

    /**
     * @return string
     */
    private function getExpectedResponsesFolder()
    {
        return (isset($_SERVER['EXPECTED_RESPONSE_DIR'])) ? $this->getRootDir() . $_SERVER['EXPECTED_RESPONSE_DIR'] : $this->guessResponsesFolder() . '/Expected';
    }

    /**
     * @return string
     */
    private function getMockedResponsesFolder()
    {
        return (isset($_SERVER['MOCKED_RESPONSE_DIR'])) ? $this->getRootDir() . $_SERVER['MOCKED_RESPONSE_DIR'] : $this->guessResponsesFolder() . '/Mocked';
    }

    /**
     * @return string
     */
    private function guessResponsesFolder()
    {
        $calledClass =  get_called_class();
        $calledClassFolder = dirname((new \ReflectionClass($calledClass))->getFileName());
        $responsesFolder = $calledClassFolder . '/../Responses';
        if (file_exists($responsesFolder)) {
            return $responsesFolder;
        }

        throw new \RuntimeException(sprintf('Folder %s does not exist. Please define EXPECTED_RESPONSE_DIR and MOCKED_RESPONSE_DIR variables with path to your Responses', $responsesFolder));
    }
}
