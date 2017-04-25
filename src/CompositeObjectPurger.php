<?php
namespace Lakion\ApiTestCase;

use Doctrine\Common\DataFixtures\Purger\PurgerInterface;

class CompositeObjectPurger implements PurgerInterface
{
    private $purgers;

    public function __construct(array $purgers)
    {
        $this->purgers = $purgers;
    }

    /**
     * Purge the data from the database for the given EntityManager.
     *
     * @return void
     */
    function purge()
    {
        /** @var PurgerInterface $purger */
        foreach ($this->purgers as $purger) {
            $purger->purge();
        }
    }
}
