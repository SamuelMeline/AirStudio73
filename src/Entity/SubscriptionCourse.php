<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SubscriptionCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subscription::class, inversedBy: 'subscriptionCourses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subscription $subscription = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\Column(type: 'integer')]
    private int $remainingCredits;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;

        return $this;
    }

    public function getRemainingCredits(): int
    {
        return $this->remainingCredits;
    }

    public function setRemainingCredits(int $remainingCredits): self
    {
        $this->remainingCredits = $remainingCredits;

        return $this;
    }

    public function incrementCredits(int $credits): void
    {
        $this->setRemainingCredits($this->getRemainingCredits() + $credits);
    }

    public function decrementCredits(int $credits): void
    {
        $this->setRemainingCredits($this->getRemainingCredits() - $credits);
    }
}
