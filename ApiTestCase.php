<?php

namespace AppBundle\Test;

use Coduo\PHPMatcher\Factory\SimpleFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Finder\Finder;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Nelmio\Alice\Loader\Yaml as Loader;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

abstract class ApiTestCase extends BaseWebTestCase
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Client $client
     */
    protected $client;

    /**
     * @var EntityManagerInterface
     */
    protected $defaultEntityManager;

    public function __construct()
    {
        $this->defaultEntityManager = $this->get('doctrine.orm.default_entity_manager');
    }

    public function setUp()
    {
        $purger = new ORMPurger($this->defaultEntityManager);
        $purger->purge();

        $this->client = static::createClient(array(), array('HTTP_Accept' => 'application/json'));
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
     * Asserts that response has JSON content.
     *
     * @param Response $response
     */
    protected function assertJsonResponse(Response $response)
    {
        $this->assertTrue(
            $response->headers->contains('Content-Type', 'application/json'),
            $response->headers
        );
    }

    /**
     * Asserts that response has given status code.
     *
     * @param Response $response
     * @param integer $statusCode
     */
    protected function assertResponseCode(Response $response, $statusCode = Response::HTTP_OK)
    {
        $this->assertEquals($statusCode, $response->getStatusCode());
    }

    /**
     * Asserts that response has JSON content matching the one given in file.
     *
     * @param Response $response
     * @param string $filename
     */
    protected function assertJsonResponseContent(Response $response, $filename)
    {
        $expectedResponse = file_get_contents(__DIR__.sprintf('/../Tests/Responses/%s.json', $filename));
        $actualResponse = $response->getContent();

        $actualResponse = json_encode(json_decode($actualResponse), JSON_PRETTY_PRINT);

        $factory = new SimpleFactory();
        $matcher = $factory->createMatcher();

        $result = $matcher->match($actualResponse, $expectedResponse);

        if (!$result) {
            echo $matcher->getError();

            $expectedResponse = explode(PHP_EOL, (string) $expectedResponse);
            $actualResponse   = explode(PHP_EOL, (string) $actualResponse);

            $diff = new \Diff($expectedResponse, $actualResponse, array());

            $renderer = new \Diff_Renderer_Text_Unified;
            echo $diff->render($renderer);
        }

        $this->assertTrue($result);
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
    protected function assertJsonResponseMatching(Response $response, $filename = null, $statusCode = null)
    {
        $this->assertJsonResponse($response);

        if (null !== $statusCode) {
            $this->assertResponseCode($response, $statusCode);
        }

        if (null !== $filename) {
            $this->assertJsonResponseContent($response, $filename);
        }
    }

    /**
     * @param Response $response
     */
    protected function assertAccessDeniedResponse(Response $response)
    {
        $this->assertJsonResponseMatching($response, 'authentication/access_denied_response', Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param Response $response
     */
    protected function assertNotFoundResponse(Response $response)
    {
        $this->assertJsonResponseMatching($response, 'error/not_found_response', Response::HTTP_NOT_FOUND);
    }

    /**
     * @param Response $response
     */
    protected function assertValidationFailResponse(Response $response)
    {
        $this->assertJsonResponseMatching($response, 'error/validation_fail_response', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Response $response
     */
    protected function assertBadRequestResponse(Response $response)
    {
        $this->assertJsonResponseMatching($response, 'error/bad_request_response', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Response $response
     */
    protected function assertErrorResponse(Response $response)
    {
        $this->assertJsonResponseMatching($response, 'error/error_response');
    }

    /**
     * @param Response $response
     * @param string $filename
     */
    protected function assertSuccessfulGetResponse(Response $response, $filename)
    {
        $this->assertJsonResponseMatching($response, $filename, Response::HTTP_OK);
    }

    /**
     * @param Response $response
     * @param string $filename
     */
    protected function assertSuccessfulCreateResponse(Response $response, $filename)
    {
        $this->assertJsonResponseMatching($response, $filename, Response::HTTP_CREATED);
    }

    /**
     * @param Response $response
     * @param string|null $filename
     */
    protected function assertSuccessfulUpdateResponse(Response $response, $filename = null)
    {
        if (null === $filename) {
            $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);
        } else {
            $this->assertJsonResponseMatching($response, $filename, Response::HTTP_OK);
        }
    }

    /**
     * @param Response $response
     * @param string|null $filename
     */
    protected function assertSuccessfulPartialUpdateResponse(Response $response, $filename = null)
    {
        $this->assertSuccessfulUpdateResponse($response, $filename);
    }

    /**
     * @param Response $response
     */
    protected function assertSuccessfulDeleteResponse(Response $response)
    {
        $this->assertResponseCode($response, Response::HTTP_NO_CONTENT);
    }

    /**
     * Gets service from DIC.
     *
     * @param $id
     *
     * @return object
     */
    protected function get($id)
    {
        return static::createClient()->getContainer()->get($id);
    }

    protected static function getKernelClass()
    {
        if (isset($_SERVER['KERNEL_DIR'])) {
            $dir = $_SERVER['KERNEL_DIR'];

            if (!is_dir($dir)) {
                $phpUnitDir = static::getPhpUnitXmlDir();
                if (is_dir("$phpUnitDir/$dir")) {
                    $dir = "$phpUnitDir/$dir";
                }
            }
        } else {
            $dir = static::getPhpUnitXmlDir();
        }

        $finder = new Finder();
        $finder->name('AppKernel.php')->depth(0)->in($dir);
        $results = iterator_to_array($finder);
        if (!count($results)) {
            throw new \RuntimeException('Either set KERNEL_DIR in your phpunit.xml according to http://symfony.com/doc/current/book/testing.html#your-first-functional-test or override the WebTestCase::createKernel() method.');
        }

        $file = current($results);
        $class = $file->getBasename('.php');

        require_once $file;

        return $class;
    }
}