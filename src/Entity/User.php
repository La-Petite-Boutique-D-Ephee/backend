<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(["read:collection:User"])]
    private ?UuidV6  $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[NotBlank(message: "L'email est obligatoire")]
    #[Email(message: "L'email n'est pas valide")]
    #[Length(
        max: 255,
        maxMessage: "L'email ne peux pas dépasser {{ limit }} caractères."
    )]
    #[Groups(["read:item:User"])]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[NotBlank(message: "Le mot de passe est obligatoire")]
    #[Length(
        min: 12,
        max: 255,
        minMessage: "Le mot de passe doit avoir {{ limit }} caractères de longueur.",
        maxMessage: "Le mot de passe ne peux pas dépasser {{ limit }} caractères."
    )]
    #[Regex(
        pattern: '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[#@.\/+-])/',
        message: 'Password must contain at least one minuscule, one majuscule, one number and one special char (#@./+-)'
    )]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    #[NotBlank(message: 'Le prénom est obligatoire.')]
    #[Length(
        max: 50,
        maxMessage: 'Le prénom ne peux pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(["read:item:User", "show:item:Article"])]
    private ?string $firstname = null;

    #[ORM\Column(length: 50)]
    #[NotBlank(message: 'Le nom est obligatoire.')]
    #[Length(
        max: 50,
        maxMessage: 'Le nom ne peux pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(["read:item:User"])]
    private ?string $lastname = null;

    #[ORM\Column]
    #[Groups(["read:item:User"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(["read:item:User"])]
    private ?\DateTimeImmutable $updatedAt = null;

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BANNED = 'banned';
    public const STATUS_ACTIVE = 'active';
    #[ORM\Column(length: 25)]
    private ?string $status = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Article::class, orphanRemoval: true)]
    #[Groups(["read:item:User"])]
    private Collection $articles;

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
        $this->updatedAt = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
        $this->articles = new ArrayCollection();
    }

    #[Orm\PreUpdate]
    #[Orm\PrePersist]
    public function updatedTimestamps(): void
    {
        $this->setUpdatedAt(new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris")));
        if ($this->getCreatedAt() === null) {
            $this->setCreatedAt(new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris")));
        }
    }

    public function getId(): ?UuidV6
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

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
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

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_INACTIVE, self::STATUS_BANNED, self::STATUS_ACTIVE])) {
            throw new \InvalidArgumentException("Invalid status value: $status");
        }

        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setUser($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getUser() === $this) {
                $article->setUser(null);
            }
        }

        return $this;
    }
}
