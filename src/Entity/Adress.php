<?php

namespace App\Entity;

use App\Repository\AdressRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdressRepository::class)]
class Adress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $City = null;

    #[ORM\Column]
    private ?int $Zipcode = null;

    #[ORM\Column(length: 500)]
    private ?string $Street1 = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $Street2 = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne(inversedBy: 'adresses')]
    private ?User $User = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'deliveryAdress')]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCity(): ?string
    {
        return $this->City;
    }

    public function setCity(string $City): static
    {
        $this->City = $City;

        return $this;
    }

    public function getZipcode(): ?int
    {
        return $this->Zipcode;
    }

    public function setZipcode(int $Zipcode): static
    {
        $this->Zipcode = $Zipcode;

        return $this;
    }

    public function getStreet1(): ?string
    {
        return $this->Street1;
    }

    public function setStreet1(string $Street1): static
    {
        $this->Street1 = $Street1;

        return $this;
    }

    public function getStreet2(): ?string
    {
        return $this->Street2;
    }

    public function setStreet2(?string $Street2): static
    {
        $this->Street2 = $Street2;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User): static
    {
        $this->User = $User;

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setDeliveryAdress($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getDeliveryAdress() === $this) {
                $order->setDeliveryAdress(null);
            }
        }

        return $this;
    }
}
