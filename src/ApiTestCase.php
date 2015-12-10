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

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Nelmio\Alice\Fixtures\Loader;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Coduo\PHPMatcher\Factory\SimpleFactory;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Paweł Jędrzęjewski <pawel.jedrzejewski@lakion.com>
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 */
abstract class ApiTestCase extends WebTestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $expectedResponsesPath;

    /**
     * @var string
     */
    protected $mockedResponsesPath;

    /**
     * @var string
     */
    protected $dataFixturesPath;

    public function setUp()
    {
        $this->client = static::createClient();
        $this->entityManager = $this->get('doctrine.orm.entity_manager');
    }

    public function tearDown()
    {
        if (null !== $this->client) {
            foreach ($this->client->getContainer()->getMockedServices() as $id => $service) {
                $this->client->getContainer()->unmock($id);
            }
        }

        \Mockery::close();
        $this->client = null;

        parent::tearDown();
    }

    protected function purgeDataBase()
    {
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        $this->entityManager->clear();
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
        $this->assertEquals($statusCode, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @param Response $response
     * @param string $contentType
     */
    protected function assertHeader(Response $response, $contentType)
    {
        $this->assertTrue(
            $response->headers->contains('Content-Type', $contentType),
            $response->headers
        );
    }

    /**
     * @param string $actualResponse
     * @param string $filename
     * @param string $mimeType
     */
    protected function assertResponseContent($actualResponse, $filename, $mimeType)
    {
        $responseSource = $this->getExpectedResponsesFolder();

        $expectedResponse = file_get_contents(sprintf(
            '%s/%s.%s',
            $responseSource,
            $filename,
            $mimeType
        ));

        $factory = new SimpleFactory();
        $matcher = $factory->createMatcher();

        $result = $matcher->match($actualResponse, $expectedResponse);

        if (!$result) {
            $difference = $matcher->getError();
            $difference = $difference.PHP_EOL;

            $expectedResponse = explode(PHP_EOL, (string) $expectedResponse);
            $actualResponse = explode(PHP_EOL, (string) $actualResponse);

            $diff = new \Diff($expectedResponse, $actualResponse, array());

            $renderer = new \Diff_Renderer_Text_Unified();
            $difference = $difference.$diff->render($renderer);
            $this->fail($difference);
        }
    }

    /**
     * @param Response $response
     *
     * @throws \Exception
     */
    protected function showErrorInBrowserIfOccurred(Response $response)
    {
        if (!$response->isSuccessful()) {
            $openCommand = (isset($_SERVER['OPEN_BROWSER_COMMAND'])) ? $_SERVER['OPEN_BROWSER_COMMAND'] : 'open %s';

            $filename = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.uniqid().'.html';
            file_put_contents($filename, $response->getContent());
            system(sprintf($openCommand, escapeshellarg($filename)));

            throw new \Exception('Internal server error.');
        }
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

    protected function loadPlatformData()
    {
        $loader = new Loader();

        $directory = $this->getFixturesFolder();
        $files = $this->getFilesFromDirectory($directory);

        foreach ($files as $file) {
            $objects = $loader->load($directory.'/'.$file);
            $this->persistObjects($objects);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array $objects
     */
    private function persistObjects(array $objects)
    {
        foreach ($objects as $object) {
            $this->entityManager->persist($object);
        }
    }

    /**
     * @return string
     */
    private function getFixturesFolder()
    {
        if (null === $this->dataFixturesPath) {
            $this->dataFixturesPath =  (isset($_SERVER['FIXTURES_DIR'])) ? $this->getRootDir().$_SERVER['FIXTURES_DIR'] : $this->getCalledClassFolder().'/../DataFixtures/ORM';
        }

        return $this->dataFixturesPath;
    }

    /**
     * @return string
     */
    private function getExpectedResponsesFolder()
    {
        if (null === $this->expectedResponsesPath) {
            $this->expectedResponsesPath =  (isset($_SERVER['EXPECTED_RESPONSE_DIR'])) ? $this->getRootDir().$_SERVER['EXPECTED_RESPONSE_DIR'] : $this->getCalledClassFolder().'/../Responses/Expected';
        }

        return $this->expectedResponsesPath;
    }

    /**
     * @return string
     */
    private function getMockedResponsesFolder()
    {
        if (null === $this->mockedResponsesPath) {
            $this->mockedResponsesPath =  (isset($_SERVER['MOCKED_RESPONSE_DIR'])) ? $this->getRootDir().$_SERVER['MOCKED_RESPONSE_DIR'] : $this->getCalledClassFolder().'/../Responses/Mocked';
        }

        return $this->mockedResponsesPath;
    }

    /**
     * @return string
     */
    private function getCalledClassFolder()
    {
        $calledClass =  get_called_class();
        $calledClassFolder = dirname((new \ReflectionClass($calledClass))->getFileName());

        $this->folderExists($calledClassFolder);

        return $calledClassFolder;
    }

    /**
     * @param string $directory
     *
     * @return array
     */
    private function getFilesFromDirectory($directory)
    {
        $this->folderExists($directory);

        $files = scandir($directory);
        $files = array_diff($files, array('..', '.'));
        if (!$files) {
            throw new \RuntimeException(sprintf('There is no files to load in folder %s', $directory));
        }

        return $files;
    }

    /**
     * @param string $directory
     */
    private function folderExists($directory)
    {
        if (!file_exists($directory)) {
            throw new \RuntimeException(sprintf('Folder %s does not exist', $directory));
        }
    }

    /**
     * @return string
     */
    private function getRootDir()
    {
        return $this->get('kernel')->getRootDir();
    }
}
