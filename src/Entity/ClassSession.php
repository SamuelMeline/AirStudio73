<?php

namespace App\Entity;

use App\Repository\ClassSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClassSessionRepository::class)]
class ClassSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[ORM\Column(type: 'datetime')]
    private $startTime;

    #[ORM\Column(type: 'datetime')]
    private $endTime;

    #[ORM\Column(type: 'integer')]
    private $dayOfWeek;

    #[ORM\Column(type: 'integer')]
    private $maxParticipants;

    #[ORM\Column(type: 'integer')]
    private $currentParticipants = 0;

    #[ORM\Column(type: 'integer')]
    private $week;

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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(int $maxParticipants): self
    {
        $this->maxParticipants = $maxParticipants;

        return $this;
    }

    public function getCurrentParticipants(): ?int
    {
        return $this->currentParticipants;
    }

    public function setCurrentParticipants(int $currentParticipants): self
    {
        $this->currentParticipants = $currentParticipants;

        return $this;
    }

    public function addParticipant(): self
    {
        if ($this->currentParticipants < $this->maxParticipants) {
            $this->currentParticipants++;
        }

        return $this;
    }

    public function getWeek(): ?int
    {
        return $this->week;
    }

    public function setWeek(int $week): self
    {
        $this->week = $week;

        return $this;
    }
}
