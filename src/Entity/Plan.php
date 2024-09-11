<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PlanRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    // #[ORM\Column(type: 'string', length: 255)]
    // private ?string $duration = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $stripePriceId = null;

    // #[ORM\Column(length: 255, nullable: true)]
    // private ?string $maxPayments = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $type = null;

    #[ORM\Column(name: 'stripe_product_id', type: 'string', length: 255, nullable: true)]
    private ?string $stripeProductId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subscriptionType = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRecurring = false;

    #[ORM\OneToMany(targetEntity: PlanCourse::class, mappedBy: 'plan', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $planCourses;


    public function __construct()
    {
        $this->planCourses = new ArrayCollection();
    }

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

    // public function getDuration(): ?string
    // {
    //     return $this->duration;
    // }

    // public function setDuration(string $duration): self
    // {
    //     $this->duration = $duration;
    //     return $this;
    // }

    // public function getMaxPayments(): ?string
    // {
    //     return $this->maxPayments;
    // }

    // public function setMaxPayments(string $maxPayments): self
    // {
    //     $this->maxPayments = $maxPayments;
    //     return $this;
    // }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(string $stripePriceId): self
    {
        $this->stripePriceId = $stripePriceId;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(string $stripe_product_id): self
    {
        $this->stripeProductId = $stripe_product_id;
        return $this;
    }

    public function getSubscriptionType(): ?string
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(?string $subscriptionType): self
    {
        $this->subscriptionType = $subscriptionType;
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    /**
     * @return Collection|PlanCourse[]
     */
    public function getPlanCourses(): Collection
    {
        return $this->planCourses;
    }

    public function addPlanCourse(PlanCourse $planCourse): self
    {
        if (!$this->planCourses->contains($planCourse)) {
            $this->planCourses[] = $planCourse;
            $planCourse->setPlan($this);
        }

        return $this;
    }

    public function removePlanCourse(PlanCourse $planCourse): self
    {
        if ($this->planCourses->removeElement($planCourse)) {
            // set the owning side to null (unless already changed)
            if ($planCourse->getPlan() === $this) {
                $planCourse->setPlan(null);
            }
        }

        return $this;
    }
}
