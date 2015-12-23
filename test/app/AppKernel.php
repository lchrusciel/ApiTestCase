<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Lakion
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 */
class AppKernel extends Kernel
{
    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        return array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', array(
                'test' => null,
                'secret' => 'secret',
                'router' => array(
                    'resource' => '%kernel.root_dir%/config/routing.yml',
                ),
            ));
            $container->loadFromExtension('doctrine', array(
                'dbal' => array(
                    'driver' => 'pdo_sqlite',
                    'user' => 'root',
                    'password' => '',
                    'path' => '%kernel.root_dir%/db.sqlite',
                ),
                'orm' => array(
                    'auto_mapping' => false,
                    'mappings' => array(
                        'Lakion\ApiTestCase' => array(
                            'dir' => '%kernel.root_dir%/config/doctrine',
                            'prefix' => 'Lakion\ApiTestCase\Test\Entity',
                            'alias' => 'ApiTestCase',
                            'is_bundle' => false,
                            'type' => 'yml',
                        ),
                    ),
                ),
            ));

            $container->setDefinition('app.third_party_api_client', new Definition(
                'Lakion\ApiTestCase\Test\Service\ThirdPartyApiClient'
            ));
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerBaseClass()
    {
        return '\PSS\SymfonyMockerContainer\DependencyInjection\MockerContainer';
    }
}
