<?php

namespace App\Entity;

use App\Repository\ImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
#[Vich\Uploadable]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Vich\UploadableField(mapping: "product_images", fileNameProperty: "filename")]
    #[Assert\File(
        maxSize: "2M",
        mimeTypes: [
            "image/jpeg",
            "image/png",
            "image/webp",
            "image/avif"
        ],
        maxSizeMessage: "Le taille maximum du fichier ne doit pas dépasser 2Mo",
        mimeTypesMessage: "Format non supporté (JPEG, PNG, WEBP, AVIF uniquement)."
    )]
    private ?File $imageFile = null;
    #[ORM\Column(length: 500)]
    private ?string $filename = null;

    #[ORM\Column]
    private bool $isMain = false;

    #[ORM\ManyToOne(inversedBy: 'images')]
    private ?Product $product = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): void
    {
        $this->imageFile = $imageFile;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }


    /**
     * @return bool
     */
    public function isMain(): bool
    {
        return $this->isMain;
    }

    /**
     * @param bool $isMain
     */
    public function setIsMain(bool $isMain): void
    {
        $this->isMain = $isMain;
    }


    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
