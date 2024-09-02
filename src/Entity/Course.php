<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'integer')]
    private ?int $capacity = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $isRecurrent = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $recurrenceInterval = 7;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recurrenceDuration  = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isCanceled = false;

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

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

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

    public function getRecurrenceInterval(): ?int
    {
        return $this->recurrenceInterval;
    }

    public function setRecurrenceInterval(?int $recurrenceInterval): self
    {
        $this->recurrenceInterval = $recurrenceInterval;

        return $this;
    }

    public function getRecurrenceDuration(): ?string
    {
        return $this->recurrenceDuration;
    }

    public function setRecurrenceDuration(?string $recurrenceDuration): self
    {
        $this->recurrenceDuration  = $recurrenceDuration;

        return $this;
    }
    
    public function getIsCanceled(): bool
    {
        return $this->isCanceled;
    }

    public function setIsCanceled(bool $isCanceled): self
    {
        $this->isCanceled = $isCanceled;
        return $this;
    }
}
