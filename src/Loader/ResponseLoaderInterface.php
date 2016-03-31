<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase\Loader;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
interface ResponseLoaderInterface
{
    /**
     * @param string $source
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getResponseFromSource($source);
}
