<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse mail est déjà utilisée')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(
        message: 'Veuillez saisir un email',
    )]
    #[Assert\Email(
        message: 'Veuillez saisir une adresse email valide',
    )]
    #[Assert\Length(
        max: 180,
        maxMessage: 'Votre adresse mail ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToOne(targetEntity: Address::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Address $address = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: 'Veuillez rentrer votre prénom'
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Votre prénom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: 'Veuillez rentrer votre nom'
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Votre nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $lastname = null;

    #[ORM\Column(nullable: true)]
//    #[Assert\LessThan('today')]
    private ?\DateTime $birthday = null;

    #[ORM\Column]
    private ?bool $isActive = false;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationEmailSentAt = null;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {

        if ($address === null && $this->address !== null) {
            $this->address->setUser(null);
        }

        if ($address !== null && $address->getUser() !== $this) {
            $address->setUser($this);
        }

        $this->address = $address;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFullInfo(): string
    {
        return $this->firstname.' '.$this->lastname . ' - ' . $this->email . ' - ' . $this->phoneNumber;
    }

    public function __toString(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTime $birthday): static
    {
        $this->birthday = $birthday;

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
            $order->setUser($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }

    public function getPasswordResetAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetAt;
    }

    public function setPasswordResetAt(?\DateTimeImmutable $passwordResetAt): static
    {
        $this->passwordResetAt = $passwordResetAt;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getVerificationEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->verificationEmailSentAt;
    }

    public function setVerificationEmailSentAt(?\DateTimeImmutable $verificationEmailSentAt): static
    {
        $this->verificationEmailSentAt = $verificationEmailSentAt;

        return $this;
    }

}
