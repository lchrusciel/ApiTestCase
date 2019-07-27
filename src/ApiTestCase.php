<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase;

use ApiTestCase\Symfony\WebTestCase;
use Coduo\PHPMatcher\Matcher;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Fidry\AliceDataFixtures\LoaderInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;
use Webmozart\Assert\Assert;

abstract class ApiTestCase extends WebTestCase
{
    /**
     * @var KernelInterface
     */
    protected static $sharedKernel;

    /**
     * @var Client|null
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
     * @var MatcherFactory
     */
    protected $matcherFactory;

    /**
     * @var LoaderInterface|null
     */
    private $fixtureLoader;

    /**
     * @var EntityManager|null
     */
    private $entityManager;

    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->matcherFactory = new MatcherFactory();
    }

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
            $container = static::$sharedKernel->getContainer();
            Assert::notNull($container);

            /** @var EntityManager $entityManager */
            $entityManager = $container->get('doctrine.orm.entity_manager');
            Assert::notNull($entityManager);

            $this->entityManager = $entityManager;
            $this->entityManager->getConnection()->connect();

            /** @var LoaderInterface $fixtureLoader */
            $fixtureLoader        = $container->get('fidry_alice_data_fixtures.loader.doctrine');
            $this->fixtureLoader = $fixtureLoader;

            $this->purgeDatabase();
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->client &&
            null !== $this->client->getContainer() &&
            $this->client->getContainer() instanceof \PSS\SymfonyMockerContainer\DependencyInjection\MockerContainer
        ) {
            $container = $this->client->getContainer();
            Assert::notNull($container);

            foreach ($container->getMockedServices() as $id => $service) {
                $container->unmock($id);
            }

            \Mockery::close();
        }

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

        return parent::getKernelClass();
    }

    protected function purgeDatabase()
    {
        $purger = new ORMPurger($this->getEntityManager());
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
        $client = $this->client;
        Assert::notNull($client);

        $container = $client->getContainer();
        Assert::notNull($container);

        return $container->get($id);
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
    protected function assertHeader(Response $response, string $contentType)
    {
        $headerContentType = $response->headers->get('Content-Type');
        Assert::string($headerContentType);

        self::assertContains( $contentType, $headerContentType, $response->headers );
    }

    /**
     * @param string $actualResponse
     * @param string $filename
     * @param string $mimeType
     */
    protected function assertResponseContent($actualResponse, $filename, $mimeType)
    {
        $responseSource = $this->getExpectedResponsesFolder();

        $contents  = file_get_contents(PathBuilder::build($responseSource, sprintf('%s.%s', $filename, $mimeType)));
        Assert::string($contents);

        $expectedResponse = trim($contents);

        $matcher = $this->buildMatcher();
        $actualResponse = trim($actualResponse);
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
    protected function getJsonResponseFixture(string $filename)
    {
        $responseSource = $this->getMockedResponsesFolder();

        $fileContent = file_get_contents(PathBuilder::build($responseSource, $filename.'.json'));
        Assert::string($fileContent);

        return json_decode($fileContent, true);
    }

    /**
     * @param string $source
     *
     * @return array
     */
    protected function loadFixturesFromDirectory(string $source = '')
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

        return $this->getFixtureLoader()->load(array_filter($files));
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

        return $this->getFixtureLoader()->load([$source]);
    }

    /**
     * @param array $sources
     *
     * @return array
     */
    protected function loadFixturesFromFiles(array $sources)
    {
        $realPaths = array();

        foreach ($sources as $source) {
            $source = $this->getFixtureRealPath($source);
            $this->assertSourceExists($source);

            $realPaths[] = $source;
        }

        return $this->getFixtureLoader()->load($realPaths);
    }

    /**
     * @return LoaderInterface
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
    protected function getEntityManager(): EntityManager
    {
        $entityManager = $this->entityManager;
        if (null === $entityManager || !$entityManager->getConnection()->isConnected()) {
            static::fail('Could not establish test database connection.');

            // PHPStan can not figure out that this part of the code should never be reached
            throw new InvalidArgumentException('Could not establish test database connection.');
        }

        return $entityManager;
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
                PathBuilder::build($this->getRootDir(), $_SERVER['FIXTURES_DIR']) :
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
        $calledClass       = get_called_class();

        /** @var string $fileName */
        $fileName          = (new \ReflectionClass($calledClass))->getFileName();
        $calledClassFolder = dirname($fileName);

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
        /** @var KernelInterface $kernel
         */
        $kernel = $this->get('kernel');
        return $kernel->getRootDir();
    }
}
