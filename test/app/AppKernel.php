<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase\Test\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Fidry\AliceDataFixtures\Bridge\Symfony\FidryAliceDataFixturesBundle;
use Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Definition;
use ApiTestCase\Test\Controller\SampleController;

class AppKernel extends Kernel
{
    /**
     * {@inheritdoc}
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new NelmioAliceBundle(),
            new FidryAliceDataFixturesBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => null,
                'secret' => 'secret',
                'router' => [
                    'resource' => '%kernel.project_dir%/app/config/routing.yml',
                ],
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'user' => 'root',
                    'password' => '',
                    'path' => '%kernel.cache_dir%/db.sqlite',
                ],
                'orm' => [
                    'auto_mapping' => false,
                    'mappings' => [
                        'ApiTestCase' => [
                            'dir' => '%kernel.project_dir%/app/config/doctrine',
                            'prefix' => 'ApiTestCase\Test\Entity',
                            'alias' => 'ApiTestCase',
                            'is_bundle' => false,
                            'type' => 'yml',
                        ],
                    ],
                ],
            ]);

            $controllerDefinition = new Definition(SampleController::class);
            $controllerDefinition->setPublic(true);
            $controllerDefinition->setAutowired(true);

            $container->setDefinition(SampleController::class, $controllerDefinition);
        });
    }
}
