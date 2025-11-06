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

namespace ApiTestCase\Trait;

use ApiTestCase\PathBuilder;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\ProcessorInterface;
use Symfony\Component\Finder\Finder;
use Webmozart\Assert\Assert;

/**
 * Provides fixture loading functionality for API tests.
 *
 * Features:
 * - Load fixtures from single file
 * - Load fixtures from multiple files
 * - Load all fixtures from directory
 * - Support for custom fixture processors
 *
 * Requires:
 * - Doctrine ORM support enabled
 * - Alice Data Fixtures library
 *
 * Usage:
 * - Use this trait in your test class
 * - Ensure setupFixtureLoader() is called during test setup
 */
trait FixtureLoading
{
    /** @var LoaderInterface|null */
    private $fixtureLoader;

    /**
     * Initializes the fixture loader from the service container.
     * Should be called from a @before hook in the parent class.
     */
    protected function setupFixtureLoader(): void
    {
        $container = static::$sharedKernel->getContainer();
        Assert::notNull($container);

        /** @var LoaderInterface $fixtureLoader */
        $fixtureLoader = $container->get('fidry_alice_data_fixtures.loader.doctrine');
        $this->fixtureLoader = $fixtureLoader;
    }

    /**
     * @return ProcessorInterface[]
     */
    protected function getFixtureProcessors(): array
    {
        return [];
    }

    protected function loadFixturesFromDirectory(string $source = ''): array
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

    protected function loadFixturesFromFile(string $source): array
    {
        $source = $this->getFixtureRealPath($source);
        $this->assertSourceExists($source);

        return $this->getFixtureLoader()->load([$source]);
    }

    /**
     * @param string[] $sources
     *
     * @return object[]
     */
    protected function loadFixturesFromFiles(array $sources): array
    {
        $realPaths = [];

        foreach ($sources as $source) {
            $source = $this->getFixtureRealPath($source);
            $this->assertSourceExists($source);

            $realPaths[] = $source;
        }

        return $this->getFixtureLoader()->load($realPaths);
    }

    protected function getFixtureLoader(): LoaderInterface
    {
        if (null === $this->fixtureLoader) {
            throw new \RuntimeException('Please, set up a database before you will try to use a fixture loader');
        }

        return $this->fixtureLoader;
    }

    protected function tearDownFixtureLoader(): void
    {
        $this->fixtureLoader = null;
    }

    private function getFixtureRealPath(string $source): string
    {
        $baseDirectory = $this->getFixturesFolder();

        return PathBuilder::build($baseDirectory, $source);
    }

    /**
     * Gets the fixtures folder path.
     * This method should be provided by the class using this trait or PathResolution trait.
     *
     * @return string
     */
    abstract protected function getFixturesFolder(): string;

    /**
     * Asserts that a source file or directory exists.
     * This method should be provided by the class using this trait or PathResolution trait.
     *
     * @param string $source
     * @return void
     */
    abstract protected function assertSourceExists(string $source): void;
}
