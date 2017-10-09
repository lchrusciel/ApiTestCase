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

use Coduo\PHPMatcher\Matcher;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Nelmio\Alice\Loader\NativeLoader;
use Nelmio\Alice\Persister\Doctrine;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Paweł Jędrzęjewski <pawel.jedrzejewski@lakion.com>
 * @author Michał Marcinkowski <michal.marcinkowski@lakion.com>
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
abstract class ApiTestCase extends WebTestCase
{
    /**
     * @var Kernel
     */
    protected static $sharedKernel;

    /**
     * @var Client
     */
    protected $client;

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
     * @var NativeLoader
     */
    private $fixtureLoader;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @beforeClass
     */
    public static function createSharedKernel()
    {
        static::$sharedKernel = static::createKernel(['debug' => false]);
        static::$sharedKernel->boot();
    }

    /**
     * @afterClass
     */
    public static function ensureSharedKernelShutdown()
    {
        if (null !== static::$sharedKernel) {
            $container = static::$sharedKernel->getContainer();
            static::$sharedKernel->shutdown();
            if ($container instanceof ResettableContainerInterface) {
                $container->reset();
            }
        }
    }

    /**
     * @before
     */
    public function setUpClient()
    {
        $this->client = static::createClient(['debug' => false]);
    }

    /**
     * @before
     */
    public function setUpDatabase()
    {
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $this->entityManager = static::$sharedKernel->getContainer()->get('doctrine.orm.entity_manager');
            $this->entityManager->getConnection()->connect();

//             $this->fixtureLoader = new Fixtures(new Doctrine($this->getEntityManager()), [], $this->getFixtureProcessors());
            $this->fixtureLoader = static::$sharedKernel->getContainer()->get('fidry_alice_data_fixtures.doctrine.persister_loader');

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
        $this->entityManager = null;
        $this->fixtureLoader = null;

        parent::tearDown();
    }

    /**
     * @return Matcher
     */
    abstract protected function buildMatcher();

    /**
     * return ProcessorInterface[]
     */
    protected function getFixtureProcessors()
    {
        return [];
    }

    protected static function getKernelClass()
    {
        if (isset($_SERVER['KERNEL_CLASS'])) {
            return '\\' . ltrim($_SERVER['KERNEL_CLASS'], '\\');
        }

        if (isset($_SERVER['KERNEL_CLASS_PATH'])) {
            $paths = [
                $_SERVER['KERNEL_CLASS_PATH'],
                static::getPhpUnitXmlDir() . DIRECTORY_SEPARATOR . $_SERVER['KERNEL_CLASS_PATH']
            ];

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    require_once $path;

                    return '\\' . (new \SplFileInfo($path))->getBasename('.php');
                }
            }
        }

        return parent::getKernelClass();
    }

    protected function purgeDatabase($mode=2)
    {
        $purger = new ORMPurger($this->getEntityManager());
        $purger->setPurgeMode($mode);
        $purger->purge();

        $this->getEntityManager()->clear();
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
        if (null === $this->client) {
            throw new \RuntimeException('Please, set up the client before you will try to load a service from the container');
        }

        return static::$kernel->getContainer()->get($id);
    }

    /**
     * @param Response $response
     * @param int $statusCode
     */
    protected function assertResponseCode(Response $response, $statusCode)
    {
        self::assertEquals($statusCode, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @param Response $response
     * @param string $contentType
     */
    protected function assertHeader(Response $response, $contentType)
    {
        self::assertTrue(
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

        $actualResponse = trim($actualResponse);
        $expectedResponse = trim(file_get_contents(PathBuilder::build($responseSource, sprintf('%s.%s', $filename, $mimeType))));

        $matcher = $this->buildMatcher();
        $result = $matcher->match($actualResponse, $expectedResponse);

        if (!$result) {
            $diff = new \Diff(explode(PHP_EOL, $expectedResponse), explode(PHP_EOL, $actualResponse), []);

            self::fail($matcher->getError() . PHP_EOL . $diff->render(new \Diff_Renderer_Text_Unified()));
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
            $openCommand = isset($_SERVER['OPEN_BROWSER_COMMAND']) ? $_SERVER['OPEN_BROWSER_COMMAND'] : 'open %s';
            $tmpDir = isset($_SERVER['TMP_DIR']) ? $_SERVER['TMP_DIR'] : sys_get_temp_dir();
            
            $filename = PathBuilder::build(rtrim($tmpDir, \DIRECTORY_SEPARATOR), uniqid() . '.html');
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

        return json_decode(file_get_contents(PathBuilder::build($responseSource, $filename . '.json')), true);
    }

    /**
     * @param string $source
     *
     * @return array
     */
    protected function loadFixturesFromDirectory($source = '')
    {
        $source = $this->getFixtureRealPath($source);
        $this->assertSourceExists($source);

        $finder = new Finder();
        $finder->files()->name('*.yml')->in($source);

        if (0 === $finder->count()) {
            throw new \RuntimeException(sprintf('There is no files to load in folder %s', $source));
        }

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $this->getFixtureLoader()->load($files);
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

        $file[] = $source;
        return $this->getFixtureLoader()->load($file);
    }

    /**
     * @param string[] $source
     *
     * @return array
     */
    protected function loadFixturesFromFiles($sources)
    {
        foreach ($sources as $source) {
            $path = $this->getFixtureRealPath($source);
            $paths[] = $path;
            $this->assertSourceExists($path);
        }

        return $this->getFixtureLoader()->load($paths);
    }

    /**
     * @return Fixtures
     */
    protected function getFixtureLoader()
    {
        if (null === $this->fixtureLoader) {
            throw new \RuntimeException('Please, set up a database before you will try to use a fixture loader');
        }

        return $this->fixtureLoader;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        if (null === $this->entityManager || !$this->entityManager->getConnection()->isConnected()) {
            static::fail('Could not establish test database connection.');
        }

        return $this->entityManager;
    }

    /**
     * @param string $source
     *
     * @return string
     */
    private function getFixtureRealPath($source)
    {
        $baseDirectory = $this->getFixturesFolder();

        return PathBuilder::build($baseDirectory, $source);
    }

    /**
     * @return string
     */
    private function getFixturesFolder()
    {
        if (null === $this->dataFixturesPath) {
            $this->dataFixturesPath = isset($_SERVER['FIXTURES_DIR']) ?
                PathBuilder::build($this->getRootDir(), $_SERVER['FIXTURES_DIR'] ) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'DataFixtures', 'ORM');
        }

        return $this->dataFixturesPath;
    }

    /**
     * @return string
     */
    private function getExpectedResponsesFolder()
    {
        if (null === $this->expectedResponsesPath) {
            $this->expectedResponsesPath = isset($_SERVER['EXPECTED_RESPONSE_DIR']) ?
                PathBuilder::build($this->getRootDir(), $_SERVER['EXPECTED_RESPONSE_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'Responses', 'Expected');
        }

        return $this->expectedResponsesPath;
    }

    /**
     * @return string
     */
    private function getMockedResponsesFolder()
    {
        if (null === $this->mockedResponsesPath) {
            $this->mockedResponsesPath = isset($_SERVER['MOCKED_RESPONSE_DIR']) ?
                PathBuilder::build($this->getRootDir(), $_SERVER['MOCKED_RESPONSE_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'Responses', 'Mocked');
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
     */
    private function assertSourceExists($source)
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File %s does not exist', $source));
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
