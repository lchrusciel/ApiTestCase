<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase\Symfony;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Webmozart\Assert\Assert;

/**
 * WebTestCase is the base class for functional tests.
 */
abstract class WebTestCase extends KernelTestCase
{
    /**
     * Creates a Client.
     *
     * @param array $options An array of options to pass to the createKernel method
     * @param array $server  An array of server parameters
     *
     * @return KernelBrowser A Client instance
     */
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $kernel = static::bootKernel($options);

        try {
            $container = $kernel->getContainer();
            Assert::notNull($container);

            /** @var KernelBrowser $client */
            $client = $container->get('test.client');
        } catch (ServiceNotFoundException $e) {
            throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit component is not available. Try running "composer require symfony/browser-kit".');
        }

        $client->setServerParameters($server);

        return $client;
    }
}
