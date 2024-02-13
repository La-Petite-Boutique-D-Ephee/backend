<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\UuidV6;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(["user:read", "article:collection"])]
    private ?UuidV6  $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["user:read", "article:collection", "article:show"])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["user:read", "article:collection", "article:show"])]
    private ?string $content = null;

    #[ORM\Column]
    #[Groups(["article:collection"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    #[Groups(["article:collection"])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["article:show"])]
    private ?User $user = null;

    #[Vich\UploadableField(mapping: 'articles', fileNameProperty: 'thumbnail')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["article:show", "article:collection"])]
    private ?string $thumbnail = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
        $this->updatedAt = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
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

    #[Orm\PreUpdate]
    #[Orm\PrePersist]
    public function updatedSlug(): void
    {
        $slugger = new AsciiSlugger();
        $this->setSlug($slugger->slug(strtolower($this->getTitle())));
    }

    public function getId(): ?UuidV6
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
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

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
        }
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail = null): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }
}
