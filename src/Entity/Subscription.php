<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plan $plan = null;

    #[ORM\Column(type: 'date')] // Changer en 'date'
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: 'date', nullable: true)] // Changer en 'date'
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: 'date', nullable: true)] // Changer en 'date'
    private ?\DateTimeInterface $startDate = null; // Ajouter le champ startDate

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentMode = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $paymentInstallments = 1;

    #[ORM\Column(type: 'integer')]
    private int $paymentsCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $maxPayments = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'stripe_product_id', type: 'string', length: 255, nullable: true)]
    private ?string $stripeProductId = null;

    #[ORM\Column(name: 'stripe_subscription_id', type: 'string', length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $promoCode = null;

    #[ORM\OneToMany(targetEntity: SubscriptionCourse::class, mappedBy: 'subscription', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $subscriptionCourses;

    public function __construct()
    {
        $this->purchaseDate = new \DateTime();
        $this->subscriptionCourses = new ArrayCollection();
    }

    public function isValid(): bool
    {
        $currentDate = new \DateTime();
        return $this->expiryDate === null || $currentDate <= $this->expiryDate;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): self
    {
        $this->plan = $plan;

        // Assigner la startDate et la endDate depuis le Plan
        if ($plan->getStartDate() !== null) {
            $this->startDate = $plan->getStartDate();
        }

        if ($plan->getEndDate() !== null) {
            $this->expiryDate = $plan->getEndDate(); // Vérifiez ici
        } else {
            throw new \Exception("Plan does not have an end date.");
        }

        return $this;
    }


    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(\DateTimeInterface $purchaseDate): self
    {
        $this->purchaseDate = $purchaseDate;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
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

    public function getPaymentMode(): ?string
    {
        return $this->paymentMode;
    }

    public function setPaymentMode(?string $paymentMode): self
    {
        $this->paymentMode = $paymentMode;
        return $this;
    }

    // Getter et setter pour la propriété paymentInstallments
    public function getPaymentInstallments(): ?int
    {
        return $this->paymentInstallments;
    }

    public function setPaymentInstallments(?int $paymentInstallments): self
    {
        $this->paymentInstallments = $paymentInstallments;
        return $this;
    }

    public function incrementPaymentsCount(): self
    {
        $this->paymentsCount++;
        return $this;
    }

    public function getPaymentsCount(): int
    {
        return $this->paymentsCount;
    }

    public function setPaymentsCount(int $paymentsCount): self
    {
        $this->paymentsCount = $paymentsCount;
        return $this;
    }

    public function getMaxPayments(): int
    {
        return $this->maxPayments;
    }

    public function setMaxPayments(int $maxPayments): self
    {
        $this->maxPayments = $maxPayments;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
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

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(string $stripe_subscription_id): self
    {
        $this->stripeSubscriptionId = $stripe_subscription_id;
        return $this;
    }

    public function getPromoCode(): ?string
    {
        return $this->promoCode;
    }

    public function setPromoCode(?string $promoCode): self
    {
        $this->promoCode = $promoCode;
        return $this;
    }

    public function getSubscriptionCourses(): Collection
    {
        return $this->subscriptionCourses;
    }

    public function addSubscriptionCourse(SubscriptionCourse $subscriptionCourse): self
    {
        if (!$this->subscriptionCourses->contains($subscriptionCourse)) {
            $this->subscriptionCourses[] = $subscriptionCourse;
            $subscriptionCourse->setSubscription($this);
        }

        return $this;
    }

    public function removeSubscriptionCourse(SubscriptionCourse $subscriptionCourse): self
    {
        if ($this->subscriptionCourses->removeElement($subscriptionCourse)) {
            if ($subscriptionCourse->getSubscription() === $this) {
                $subscriptionCourse->setSubscription(null);
            }
        }

        return $this;
    }

    public function getCourseCredits(Course $course): ?int
    {
        foreach ($this->subscriptionCourses as $subscriptionCourse) {
            if ($subscriptionCourse->getCourse() === $course) {
                return $subscriptionCourse->getRemainingCredits();
            }
        }
        return null;
    }

    public function decrementCourseCredits(Course $course, int $credits): self
    {
        foreach ($this->subscriptionCourses as $subscriptionCourse) {
            if ($subscriptionCourse->getCourse() === $course) {
                if ($subscriptionCourse->getRemainingCredits() >= $credits) {
                    $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() - $credits);
                } else {
                    throw new \Exception("Not enough credits for this course.");
                }
                break;
            }
        }

        return $this;
    }
}
