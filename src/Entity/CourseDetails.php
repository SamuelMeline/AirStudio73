<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CourseDetailsRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: CourseDetailsRepository::class)]
class CourseDetails
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $benefits = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photobenefits = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $second_benefits = null;

    #[ORM\OneToMany(mappedBy: 'courseDetails', targetEntity: Review::class, orphanRemoval: true)]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection(); // Initialisation de la collection
    }

    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setCourseDetails($this);
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getCourseDetails() === $this) {
                $review->setCourseDetails(null);
            }
        }

        return $this;
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }


    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;
        return $this;
    }

    public function getBenefits(): ?string
    {
        return $this->benefits;
    }

    public function setBenefits(?string $benefits): self
    {
        $this->benefits = $benefits;
        return $this;
    }

    public function getPhotoBenefits(): ?string
    {
        return $this->photobenefits;
    }

    public function setPhotoBenefits(?string $photobenefits): self
    {
        $this->photobenefits = $photobenefits;
        return $this;
    }

    public function getSecondBenefits(): ?string
    {
        return $this->second_benefits;
    }

    public function setSecondBenefits(?string $second_benefits): self
    {
        $this->second_benefits = $second_benefits;
        return $this;
    }
}
