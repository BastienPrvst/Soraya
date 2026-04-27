<?php

namespace App\Entity;

use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTime $creationDate = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $orderItems;

    #[ORM\Column]
    private ?float $total = null;

    #[ORM\Column(enumType: OrderStatus::class)]
    private ?OrderStatus $status = OrderStatus::CREATED;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Veuillez saisir une adresse mail')]
    #[Assert\Email(message: 'Veuillez saisir une adresse mail valide')]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'Veuillez saisir un numéro de telephone')]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre prénom')]
    #[Assert\Length(min: 1, max: 255, maxMessage: 'Votre prénom ne peut pas excéder 255 caractères')]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre nom')]
    #[Assert\Length(min: 1, max: 255, maxMessage: 'Votre nom ne peut pas excéder 255 caractères')]
    private ?string $lastname = null;
    #[ORM\OneToOne(mappedBy: 'RelatedOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Payment $payment = null;

    #[ORM\Column]
    private ?bool $delivery = null;

    #[ORM\ManyToOne(
        cascade: ['persist'],
        inversedBy: 'orders'
    )]
    #[Assert\Valid]
    private ?Address $deliveryAddress = null;

    #[ORM\Column(nullable: true)]
    private ?float $deliveryPrice = null;

    #[ORM\Column(enumType: DeliveryMode::class)]
    private ?DeliveryMode $deliveryMode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $relayId = null;

    #[ORM\ManyToOne]
    private ?Address $relayAddress = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $sessionKey = null;

    #[ORM\ManyToOne]
    private ?Address $BillingAddress = null;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreationDate(): ?\DateTime
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTime $creationDate): static
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function isDelivery(): ?bool
    {
        return $this->delivery;
    }

    public function setDelivery(bool $delivery): static
    {
        $this->delivery = $delivery;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getStatus(): ?OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getDeliveryPrice(): ?float
    {
        return $this->deliveryPrice;
    }

    public function setDeliveryPrice(?float $deliveryPrice): static
    {
        $this->deliveryPrice = $deliveryPrice;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): void
    {
        $this->payment = $payment;
    }

    public function getDeliveryMode(): ?DeliveryMode
    {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(DeliveryMode $deliveryMode): static
    {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getDeliveryModeLabel(): string
    {
        return $this->deliveryMode->label();
    }

    public function getDeliveryAddress(): ?Address
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?Address $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getRelayId(): ?string
    {
        return $this->relayId;
    }

    public function setRelayId(?string $relayId): static
    {
        $this->relayId = $relayId;

        return $this;
    }

    public function getRelayAddress(): ?Address
    {
        return $this->relayAddress;
    }

    public function setRelayAddress(?Address $relayAddress): static
    {
        $this->relayAddress = $relayAddress;

        return $this;
    }

    public function getActiveAddress(): ?Address
    {
        if ($this->getDeliveryMode() === DeliveryMode::RELAY) {
            return $this->getRelayAddress();
        }

        return $this->getDeliveryAddress();
    }

    public function getBillingAddress(): ?Address
    {
        return $this->BillingAddress;
    }

    public function setBillingAddress(?Address $BillingAddress): void
    {
        $this->BillingAddress = $BillingAddress;
    }

    public function setActiveAddress(Address $address): void
    {
        if ($this->getDeliveryMode() === DeliveryMode::RELAY) {
            $this->setRelayAddress($address);
        } else {
            $this->setDeliveryAddress($address);
        }
    }

    public function getSessionKey(): ?string
    {
        return $this->sessionKey;
    }

    public function setSessionKey(string $sessionKey): static
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }
}
