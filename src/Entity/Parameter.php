<?php

namespace App\Entity;

use App\Repository\ParameterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParameterRepository::class)]
class Parameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminMail = null;

    #[ORM\Column(nullable: true)]
    private ?int $criticalStock = 40;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdminMail(): ?string
    {
        return $this->adminMail;
    }

    public function setAdminMail(?string $adminMail = null): static
    {
        $this->adminMail = $adminMail;

        return $this;
    }

    public function getCriticalStock(): ?int
    {
        return $this->criticalStock;
    }

    public function setCriticalStock(int $criticalStock = 40): static
    {
        $this->criticalStock = $criticalStock;

        return $this;
    }
}
