<?php

namespace App\Entity;

use App\Repository\MatchesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MatchesRepository::class)]
#[ORM\Table(name: 'matches')]
class Matches
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'matchs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournoi $tournoi = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Team 1 name is required.")]
    private ?string $equipe1 = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Team 2 name is required.")]
    private ?string $equipe2 = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Score is required.")]
    private ?int $score = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Match date is required.")]
    private ?\DateTimeInterface $dateMatch = null;

    #[Assert\Callback]
    public function validateDate(ExecutionContextInterface $context, mixed $payload): void
    {
        if ($this->tournoi && $this->dateMatch) {
            $dateDebut = $this->tournoi->getDateDebut();
            $dateFin = $this->tournoi->getDateFin();

            if ($dateDebut && $this->dateMatch < $dateDebut) {
                $context->buildViolation('The match date cannot be before the tournament start date ({{ date }}).')
                    ->setParameter('{{ date }}', $dateDebut->format('Y-m-d'))
                    ->atPath('dateMatch')
                    ->addViolation();
            }

            if ($dateFin && $this->dateMatch > $dateFin) {
                $context->buildViolation('The match date cannot be after the tournament end date ({{ date }}).')
                    ->setParameter('{{ date }}', $dateFin->format('Y-m-d'))
                    ->atPath('dateMatch')
                    ->addViolation();
            }
        }
    }

    #[ORM\Column(enumType: Prix::class)]
    private ?Prix $prix = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournoi(): ?Tournoi
    {
        return $this->tournoi;
    }

    public function setTournoi(?Tournoi $tournoi): static
    {
        $this->tournoi = $tournoi;

        return $this;
    }

    public function getEquipe1(): ?string
    {
        return $this->equipe1;
    }

    public function setEquipe1(string $equipe1): static
    {
        $this->equipe1 = $equipe1;

        return $this;
    }

    public function getEquipe2(): ?string
    {
        return $this->equipe2;
    }

    public function setEquipe2(string $equipe2): static
    {
        $this->equipe2 = $equipe2;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getDateMatch(): ?\DateTimeInterface
    {
        return $this->dateMatch;
    }

    public function setDateMatch(\DateTimeInterface $dateMatch): static
    {
        $this->dateMatch = $dateMatch;

        return $this;
    }

    public function getPrix(): ?Prix
    {
        return $this->prix;
    }

    public function setPrix(Prix $prix): static
    {
        $this->prix = $prix;

        return $this;
    }
}
