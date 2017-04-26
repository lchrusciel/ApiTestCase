<?php
namespace Lakion\ApiTestCase;

use Nelmio\Alice\Fixtures;

class CompositeFixtureLoader
{
    private $fixtureLoaders;

    public function __construct(array $fixtureLoaders)
    {
        $this->fixtureLoaders = $fixtureLoaders;
    }

    /**
     * @param array|string $files
     * @param array $options
     *
     * @return array
     */
    public function loadFiles($files, array $options = [])
    {
        $objects = [];

        /** @var Fixtures $fixtureLoader */
        foreach ($this->fixtureLoaders as $fixtureLoader) {
            $objects = array_merge($fixtureLoader->loadFiles($files, $options), $objects);
        }

        return $objects;
    }
}
