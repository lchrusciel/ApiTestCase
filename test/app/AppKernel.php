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

            $container->setDefinition('app.service', new Definition(
                'Lakion\ApiTestCase\Test\Service\DummyService'
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
