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
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Nelmio\Alice\Fixtures\Loader;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Paweł Jędrzęjewski <pawel.jedrzejewski@lakion.com>
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
abstract class ApiTestCase extends WebTestCase
{
    const JSON = 'application/json';
    const XML = 'application/xml';

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
     * bool
     */
    private $isDoctrineSupported = false;

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
    }

    /**
     * @before
     */
    public function setUpDatabase()
    {
        $this->isDoctrineSupported = isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED'];
        if ($this->isDoctrineSupported) {
            $this->entityManager = static::$sharedKernel->getContainer()->get('doctrine.orm.entity_manager');

            if (!$this->entityManager->getConnection()->isConnected()) {
                static::markTestSkipped('Could not establish test database connection.');
            }

            $this->purgeDatabase();
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
     * @return string
     */
    protected static function getKernelClass()
    {
        if (!isset($_SERVER['KERNEL_CLASS_PATH'])) {
            return parent::getKernelClass();
        }

        if (file_exists($_SERVER['KERNEL_CLASS_PATH'])) {
            require_once $_SERVER['KERNEL_CLASS_PATH'];

            return (new \SplFileInfo($_SERVER['KERNEL_CLASS_PATH']))->getBasename('.php');
        }

        if (file_exists(static::getPhpUnitXmlDir().DIRECTORY_SEPARATOR.$_SERVER['KERNEL_CLASS_PATH'])) {
            require_once static::getPhpUnitXmlDir().DIRECTORY_SEPARATOR.$_SERVER['KERNEL_CLASS_PATH'];

            return (new \SplFileInfo(static::getPhpUnitXmlDir().DIRECTORY_SEPARATOR.$_SERVER['KERNEL_CLASS_PATH']))->getBasename('.php');
        }
    }

    protected function purgeDatabase()
    {
        if (!$this->isDoctrineSupported) {
            throw new \RuntimeException('You are trying to purge database without Doctrine support being enabled.');
        }

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
        static::assertEquals($statusCode, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @param Response $response
     * @param string $contentType
     */
    protected function assertHeader(Response $response, $contentType)
    {
        static::assertTrue(
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

        $matcher = (new SimpleFactory())->createMatcher();

        $result = $matcher->match($actualResponse, $expectedResponse);
        if (!$result) {
            $difference = $matcher->getError();
            $difference = $difference.PHP_EOL;

            $expectedResponse = explode(PHP_EOL, (string) $expectedResponse);
            $actualResponse = explode(PHP_EOL, (string) $actualResponse);

            $diff = new \Diff($expectedResponse, $actualResponse, array());

            $difference .= $diff->render(new \Diff_Renderer_Text_Unified());
            static::fail($difference);
        }
    }

    /**
     * @param Response $response
     *
     * @throws \RuntimeException
     */
    protected function showErrorInBrowserIfOccurred(Response $response)
    {
        if (!$response->isSuccessful()) {
            $openCommand = isset($_SERVER['OPEN_BROWSER_COMMAND']) ? $_SERVER['OPEN_BROWSER_COMMAND'] : 'open %s';

            $filename = rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.uniqid().'.html';
            file_put_contents($filename, $response->getContent());
            system(sprintf($openCommand, escapeshellarg($filename)));

            throw new \RuntimeException('Internal server error.');
        }
    }

    /**
     * Provides array from decoded json file. Requires MOCKED_RESPONSE_DIR defined variable to work properly.
     *
     * @param string $filename
     *
     * @return array
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
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function loadFixturesFromDirectory($source = '')
    {
        $source = $this->getFixtureRealPath($source);

        $this->assertSourceExists($source);

        $finder = new Finder();
        $finder->files()->name('*.yml')->in($source);

        if (0 === $finder->count()) {
            throw new \RuntimeException(sprintf('There is no files to load in folder "%s"', $source));
        }

        $loader = new Loader();

        $objects = [];
        foreach ($finder as $file) {
            $objects[] = $loader->load($file->getRealPath());
        }

        $objects = $objects ? call_user_func_array('array_merge', $objects) : [];
        if ($this->isDoctrineSupported) {
            foreach ($objects as $object) {
                $this->entityManager->persist($object);
            }

            $this->entityManager->flush();
        }

        return $objects;
    }

    /**
     * @param string $source
     *
     * @return array
     */
    protected function loadFixturesFromFile($source)
    {
        $source = $this->getFixtureRealPath($source);

        $this->assertSourceExists($source);

        $objects = (new Loader())->load($source);
        if ($this->isDoctrineSupported) {
            foreach ($objects as $object) {
                $this->entityManager->persist($object);
            }

            $this->entityManager->flush();
        }

        return $objects;
    }

    /**
     * @param string $source
     *
     * @return string
     */
    private function getFixtureRealPath($source)
    {
        return $this->getFixturesFolder().DIRECTORY_SEPARATOR.$source;
    }

    /**
     * @return string
     */
    private function getFixturesFolder()
    {
        if (null === $this->dataFixturesPath) {
            $this->dataFixturesPath = isset($_SERVER['FIXTURES_DIR']) ? $this->getRootDir().$_SERVER['FIXTURES_DIR'] : $this->getCalledClassFolder().'/../DataFixtures/ORM';
        }

        return $this->dataFixturesPath;
    }

    /**
     * @return string
     */
    private function getExpectedResponsesFolder()
    {
        if (null === $this->expectedResponsesPath) {
            $this->expectedResponsesPath = isset($_SERVER['EXPECTED_RESPONSE_DIR']) ? $this->getRootDir().$_SERVER['EXPECTED_RESPONSE_DIR'] : $this->getCalledClassFolder().'/../Responses/Expected';
        }

        return $this->expectedResponsesPath;
    }

    /**
     * @return string
     */
    private function getMockedResponsesFolder()
    {
        if (null === $this->mockedResponsesPath) {
            $this->mockedResponsesPath = isset($_SERVER['MOCKED_RESPONSE_DIR']) ? $this->getRootDir().$_SERVER['MOCKED_RESPONSE_DIR'] : $this->getCalledClassFolder().'/../Responses/Mocked';
        }

        return $this->mockedResponsesPath;
    }

    /**
     * @return string
     */
    private function getCalledClassFolder()
    {
        $calledClass = get_called_class();
        $calledClassFolder = dirname((new \ReflectionClass($calledClass))->getFileName());

        $this->assertSourceExists($calledClassFolder);

        return $calledClassFolder;
    }

    /**
     * @param string $source
     *
     * @throws \RuntimeException
     */
    private function assertSourceExists($source)
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File "%s" does not exist', $source));
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
