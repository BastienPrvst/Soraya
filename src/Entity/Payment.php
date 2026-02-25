<?php

namespace App\Entity;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true, enumType: PaymentProvider::class)]
    private ?PaymentProvider $provider = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $providerId = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\OneToOne(inversedBy: 'payment')]
    #[ORM\JoinColumn(unique: true, nullable: false)]
    private ?Order $RelatedOrder = null;

    #[ORM\Column(enumType: PaymentStatus::class)]
    private ?PaymentStatus $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?PaymentProvider
    {
        return $this->provider;
    }

    public function setProvider(?PaymentProvider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): static
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->RelatedOrder;
    }

    public function setRelatedOrder(Order $RelatedOrder): static
    {
        $this->RelatedOrder = $RelatedOrder;

        return $this;
    }

    public function getStatus(): ?PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
