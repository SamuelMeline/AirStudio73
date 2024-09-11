<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PlanCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'planCourses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plan $plan = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\Column(type: 'integer')]
    private int $credits;

    #[ORM\Column(type: 'float')]
    private float $pricePerCredit;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): self
    {
        $this->plan = $plan;
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

    public function getCredits(): int
    {
        return $this->credits;
    }

    public function setCredits(int $credits): self
    {
        $this->credits = $credits;
        return $this;
    }

    public function getPricePerCredit(): float
    {
        return $this->pricePerCredit;
    }

    public function setPricePerCredit(float $pricePerCredit): self
    {
        $this->pricePerCredit = $pricePerCredit;

        return $this;
    }
}
