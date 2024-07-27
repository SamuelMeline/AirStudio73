<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $isRecurrent = null;

    #[ORM\Column(type: 'integer')]
    private ?int $numOccurrences = null;

    #[ORM\ManyToOne(targetEntity: SubscriptionCourse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $subscriptionCourse;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(Course $course): self
    {
        $this->course = $course;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getIsRecurrent(): ?bool
    {
        return $this->isRecurrent;
    }

    public function setIsRecurrent(bool $isRecurrent): self
    {
        $this->isRecurrent = $isRecurrent;

        return $this;
    }

    public function getNumOccurrences(): ?int
    {
        return $this->numOccurrences;
    }

    public function setNumOccurrences(int $numOccurrences): self
    {
        $this->numOccurrences = $numOccurrences;

        return $this;
    }

    public function getSubscriptionCourse(): ?SubscriptionCourse
    {
        return $this->subscriptionCourse;
    }

    public function setSubscriptionCourse(?SubscriptionCourse $subscriptionCourse): self
    {
        $this->subscriptionCourse = $subscriptionCourse;

        return $this;
    }
}
