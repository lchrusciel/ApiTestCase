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
use Symfony\Component\HttpKernel\Kernel;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Paweł Jędrzęjewski <pawel.jedrzejewski@lakion.com>
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
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

    /**
     * @var Kernel
     */
    static protected $sharedKernel;

    /**
     * @beforeClass
     */
    public static function createSharedKernel()
    {
        static::$sharedKernel = static::createKernel();
        static::$sharedKernel->boot();
    }

    /**
     * @before
     */
    public function setUpClient()
    {
        $this->client = static::createClient();
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $this->entityManager = static::$sharedKernel->getContainer()->get('doctrine.orm.entity_manager');
        }
    }

    /**
     * @before
     */
    public function setUpEntityManager()
    {
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $this->entityManager = static::$sharedKernel->getContainer()->get('doctrine.orm.entity_manager');
        }
    }

    public function tearDown()
    {

        if (null !== $this->client && null !== $this->client->getContainer()) {
            foreach ($this->client->getContainer()->getMockedServices() as $id => $service) {
                $this->client->getContainer()->unmock($id);
            }
        }

        \Mockery::close();
        $this->client = null;

        parent::tearDown();
    }

    /**
     * @after
     */
    protected function purgeDatabase()
    {
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $purger = new ORMPurger($this->entityManager);
            $purger->purge();

            $this->entityManager->clear();
        }
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

    /**
     * @param string $source
     */
    protected function loadFixturesFrom($source)
    {
        $loader = new Loader();

        $baseDirectory = $this->getFixturesFolder();
        $source = $baseDirectory.'/'.$source;
        $this->assertSourceExists($source);
        $paths = $this->getFixturePathsFromSource($source);

        foreach ($paths as $path) {
            $objects = $loader->load($path);
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

        $this->assertSourceExists($calledClassFolder);

        return $calledClassFolder;
    }

    /**
     * @param string $source
     *
     * @return array
     */
    private function getFixturePathsFromSource($source)
    {
        if (!is_dir($source)) {
            return array($source);
        }

        return $this->getFixturePathsFromDirectory($source);
    }

    /**
     * @param string $directory
     *
     * @return array
     */
    private function getFixturePathsFromDirectory($directory)
    {
        $filePaths = array();
        $fileNames = $this->loadFileNamesFromDirectory($directory);

        foreach ($fileNames as $fileName) {
            $filePath = $directory.'/'.$fileName;

            if (!is_dir($filePath)) {
                $filePaths[] = $filePath;
            }
        }

        return $filePaths;
    }

    /**
     * @param string $source
     */
    private function assertSourceExists($source)
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File %s does not exist', $directory));
        }
    }

    /**
     * @param string $directory
     *
     * @return array
     */
    private function loadFileNamesFromDirectory($directory)
    {
        $fileNames = scandir($directory);
        $fileNames = array_diff($fileNames, array('.', '..'));

        if (empty($fileNames)) {
            throw new \RuntimeException(sprintf('There is no files to load in folder %s', $directory));
        }

        return $fileNames;
    }

    /**
     * @return string
     */
    private function getRootDir()
    {
        return $this->get('kernel')->getRootDir();
    }
}
