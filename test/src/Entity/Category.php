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

namespace ApiTestCase\Test\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Category
{
    /** @var int|null */
    private $id;

    /** @var string */
    private $name;

    /** @var Collection|Product[] */
    private $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function addProduct(Product $product): void
    {
        $this->products->add($product);
    }

    public function removeProduct(Product $product): void
    {
        $this->products->removeElement($product);
    }

    /**
     * @return Collection|Product[]
     */
    public function getProducts()
    {
        return $this->products;
    }
}
