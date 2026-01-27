<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
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

    /**
     * @var Collection<int, Adress>
     */
    #[ORM\OneToMany(targetEntity: Adress::class, mappedBy: 'User')]
    private Collection $adresses;

    #[ORM\Column(length: 255)]
    private ?string $Firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $Lastname = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $Birthday = null;

    public function __construct()
    {
        $this->adresses = new ArrayCollection();
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

    /**
     * @return Collection<int, Adress>
     */
    public function getAdresses(): Collection
    {
        return $this->adresses;
    }

    public function addAdress(Adress $adress): static
    {
        if (!$this->adresses->contains($adress)) {
            $this->adresses->add($adress);
            $adress->setUser($this);
        }

        return $this;
    }

    public function removeAdress(Adress $adress): static
    {
        if ($this->adresses->removeElement($adress)) {
            // set the owning side to null (unless already changed)
            if ($adress->getUser() === $this) {
                $adress->setUser(null);
            }
        }

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->Firstname;
    }

    public function setFirstname(string $Firstname): static
    {
        $this->Firstname = $Firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->Lastname;
    }

    public function setLastname(string $Lastname): static
    {
        $this->Lastname = $Lastname;

        return $this;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->Birthday;
    }

    public function setBirthday(?\DateTime $Birthday): static
    {
        $this->Birthday = $Birthday;

        return $this;
    }
}
