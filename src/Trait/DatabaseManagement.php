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

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Webmozart\Assert\Assert;

/**
 * Provides database management functionality for API tests.
 *
 * Features:
 * - Database purging between tests
 * - EntityManager access
 * - Connection management
 *
 * Usage:
 * - Use this trait in your test class
 * - Ensure setupDatabaseConnection() is called during test setup
 */
trait DatabaseManagement
{
    /** @var EntityManager|null */
    private $entityManager;

    /**
     * Initializes the entity manager and purges the database.
     * Should be called from a @before hook in the parent class.
     */
    protected function setupDatabaseConnection(): void
    {
        $container = static::$sharedKernel->getContainer();
        Assert::notNull($container);

        /** @var EntityManager $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        Assert::notNull($entityManager);

        $this->entityManager = $entityManager;
        $this->entityManager->getConnection()->getNativeConnection();

        $this->purgeDatabase();
    }

    protected function tearDownDatabase(): void
    {
        $this->entityManager = null;
    }

    protected function purgeDatabase(): void
    {
        $purger = new ORMPurger($this->getEntityManager());
        $purger->purge();

        $this->getEntityManager()->clear();
    }

    protected function getEntityManager(): EntityManager
    {
        $entityManager = $this->entityManager;
        if (null === $entityManager || !$entityManager->getConnection()->isConnected()) {
            static::fail('Could not establish test database connection.');
        }

        return $entityManager;
    }
}
