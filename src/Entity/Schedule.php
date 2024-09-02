<?php

namespace App\Entity;

use App\Repository\ScheduleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScheduleRepository::class)]
class Schedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $day = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(length: 255)]
    private ?string $courseName = null;

    #[ORM\Column(length: 255)]
    private ?string $instructor = null;

    // Getters and setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(string $day): self
    {
        $this->day = $day;

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

    public function getCourseName(): ?string
    {
        return $this->courseName;
    }

    public function setCourseName(string $courseName): self
    {
        $this->courseName = $courseName;

        return $this;
    }

    public function getInstructor(): ?string
    {
        return $this->instructor;
    }

    public function setInstructor(string $instructor): self
    {
        $this->instructor = $instructor;

        return $this;
    }
}
