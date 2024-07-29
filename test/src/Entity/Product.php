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

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "test_product")]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id;

    #[ORM\Column(type: "string")]
    private string $name;

    #[ORM\Column(type: "integer")]
    private int $price;

    #[ORM\Column(type: "string")]
    private string $uuid;

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

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        $this->price = $price;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }
}
