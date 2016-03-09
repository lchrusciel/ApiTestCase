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
class ResponseLoader implements ResponseLoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getResponseFromSource($source)
    {
        $this->assertSourceExists($source);
        $this->assertSourceIsNotFolder($source);

        $loadedResponse = file_get_contents($source);
        $this->assertContentIsProperLoaded($source, $loadedResponse);

        return $loadedResponse;
    }

    /**
     * @param string $source
     *
     * @throws \RuntimeException
     */
    private function assertSourceExists($source)
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File %s does not exist', $source));
        }
    }

    /**
     * @param string $source
     * @param string $content
     *
     * @throws \RuntimeException
     */
    private function assertContentIsProperLoaded($source, $content)
    {
        if (false === $content) {
            throw new \RuntimeException(sprintf('Something went wrong, cannot open %s', $source));
        }
    }

    /**
     * @param string $source
     *
     * @throws \RuntimeException
     */
    private function assertSourceIsNotFolder($source)
    {
        if (true === is_dir($source)) {
            throw new \RuntimeException(sprintf('Given source %s is a folder!', $source));
        }
    }
}
