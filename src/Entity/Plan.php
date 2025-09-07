<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'plans')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $plan_id = null;

    #[ORM\Column(length: 255)]
    private ?string $plan_name = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $amount = null;

    #[ORM\Column(length: 50)]
    private ?string $frequency = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $day_frequency = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updated_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanId(): ?string
    {
        return $this->plan_id;
    }

    public function setPlanId(string $plan_id): static
    {
        $this->plan_id = $plan_id;
        return $this;
    }

    public function getPlanName(): ?string
    {
        return $this->plan_name;
    }

    public function setPlanName(string $plan_name): static
    {
        $this->plan_name = $plan_name;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getDayFrequency(): ?int
    {
        return $this->day_frequency;
    }

    public function setDayFrequency(int $day_frequency): static
    {
        $this->day_frequency = $day_frequency;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTime $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getDisplayName(): string
    {
        return sprintf('$%s %s (%s)', 
            number_format($this->amount, 2), 
            $this->frequency, 
            $this->plan_id
        );
    }
}
