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

use Coduo\PHPMatcher\Factory\MatcherFactory;
use Coduo\PHPMatcher\Matcher;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Webmozart\Assert\Assert;

abstract class ApiTestCase extends WebTestCase
{
    use Trait\DatabaseManagement;
    use Trait\FixtureLoading;

    /** @var KernelInterface */
    protected static $sharedKernel;

    /** @var KernelBrowser|null */
    protected $client;

    /** @var string|null */
    protected $expectedResponsesPath;

    /** @var string */
    protected $dataFixturesPath;

    /** @var MatcherFactory|null */
    protected $matcherFactory;

    /**
     * @beforeClass
     */
    public static function createSharedKernel(): void
    {
        static::$sharedKernel = static::createKernel(['debug' => false]);
        static::$sharedKernel->boot();
    }

    /**
     * @afterClass
     */
    public static function ensureSharedKernelShutdown(): void
    {
        if (null !== static::$sharedKernel) {
            $container = static::$sharedKernel->getContainer();
            static::$sharedKernel->shutdown();
            if ($container instanceof ResetInterface) {
                $container->reset();
            }
        }
    }

    /**
     * @before
     */
    public function setUpClient(): void
    {
        $this->client = static::createClient(['debug' => false]);
    }

    /**
     * @before
     */
    public function createMatcher(): void
    {
        $this->matcherFactory = new MatcherFactory();
    }

    /**
     * @before
     */
    public function setUpDatabase(): void
    {
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $this->setupDatabaseConnection();
            $this->setupFixtureLoader();
        }
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->tearDownDatabase();
        $this->tearDownFixtureLoader();

        parent::tearDown();
    }

    abstract protected function buildMatcher(): Matcher;

    protected static function getKernelClass(): string
    {
        if (isset($_SERVER['KERNEL_CLASS'])) {
            return '\\' . ltrim($_SERVER['KERNEL_CLASS'], '\\');
        }

        return parent::getKernelClass();
    }

    /**
     * Gets service from DIC.
     */
    protected function get(string $id)
    {
        $client = $this->client;
        Assert::notNull($client);

        $container = $client->getContainer();
        Assert::notNull($container);

        return $container->get($id);
    }

    protected function assertResponseCode(Response $response, int $statusCode): void
    {
        self::assertEquals($statusCode, $response->getStatusCode(), $response->getContent() ?: '');
    }

    protected function assertHeader(Response $response, string $contentType): void
    {
        $headerContentType = $response->headers->get('Content-Type');
        Assert::string($headerContentType);

        self::assertStringContainsString(
            $contentType,
            $headerContentType
        );
    }

    protected function assertResponseContent(string $actualResponse, string $filename, string $mimeType): void
    {
        $responseSource = $this->getExpectedResponsesFolder();

        $contents = file_get_contents(PathBuilder::build($responseSource, sprintf('%s.%s', $filename, $mimeType)));
        Assert::string($contents);

        $expectedResponse = trim($contents);

        $matcher = $this->buildMatcher();
        $actualResponse = trim($actualResponse);
        $result = $matcher->match($actualResponse, $expectedResponse);

        if (!$result) {
            $diff = new \Diff(explode(\PHP_EOL, $expectedResponse), explode(\PHP_EOL, $actualResponse), []);

            self::fail($matcher->getError() . \PHP_EOL . $diff->render(new \Diff_Renderer_Text_Unified()));
        }
    }

    /**
     * @throws \Exception
     */
    protected function showErrorInBrowserIfOccurred(Response $response): void
    {
        if (!$response->isSuccessful()) {
            $openCommand = $_SERVER['OPEN_BROWSER_COMMAND'] ?? 'open %s';
            $tmpDir = $_SERVER['TMP_DIR'] ?? sys_get_temp_dir();

            $filename = PathBuilder::build(rtrim($tmpDir, \DIRECTORY_SEPARATOR), uniqid() . '.html');
            file_put_contents($filename, $response->getContent());
            system(sprintf($openCommand, escapeshellarg($filename)));

            throw new \Exception('Internal server error.');
        }
    }

    protected function getFixturesFolder(): string
    {
        if (null === $this->dataFixturesPath) {
            $this->dataFixturesPath = isset($_SERVER['FIXTURES_DIR']) ?
                PathBuilder::build($this->getProjectDir(), $_SERVER['FIXTURES_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'DataFixtures', 'ORM');
        }

        return $this->dataFixturesPath;
    }

    private function getExpectedResponsesFolder(): string
    {
        if (null === $this->expectedResponsesPath) {
            $this->expectedResponsesPath = isset($_SERVER['EXPECTED_RESPONSE_DIR']) ?
                PathBuilder::build($this->getProjectDir(), $_SERVER['EXPECTED_RESPONSE_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'Responses');
        }

        return $this->expectedResponsesPath;
    }

    private function getCalledClassFolder(): string
    {
        $calledClass = get_called_class();

        /** @var string $fileName */
        $fileName = (new \ReflectionClass($calledClass))->getFileName();
        $calledClassFolder = dirname($fileName);

        $this->assertSourceExists($calledClassFolder);

        return $calledClassFolder;
    }

    protected function assertSourceExists(string $source): void
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File %s does not exist', $source));
        }
    }

    private function getProjectDir(): string
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->get('kernel');

        return $kernel->getProjectDir();
    }
}
