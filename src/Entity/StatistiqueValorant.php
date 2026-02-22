<?php

namespace App\Entity;

use App\Repository\StatistiqueValorantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatistiqueValorantRepository::class)]
#[ORM\Table(name: 'valorant_statistique')]
class StatistiqueValorant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'statistique')]
    #[ORM\JoinColumn(nullable: false)]
    private ?JoueurValorant $joueur = null;

    #[ORM\Column]
    private int $kills = 0;

    #[ORM\Column]
    private int $deaths = 0;

    #[ORM\Column]
    private int $assists = 0;

    #[ORM\Column(nullable: true)]
    private ?int $headshots = null;

    #[ORM\Column(nullable: true)]
    private ?int $damage = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $weapons = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $timings = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $extra = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoueur(): ?JoueurValorant
    {
        return $this->joueur;
    }

    public function setJoueur(?JoueurValorant $joueur): static
    {
        $this->joueur = $joueur;
        return $this;
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function setKills(int $kills): static
    {
        $this->kills = $kills;
        return $this;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function setDeaths(int $deaths): static
    {
        $this->deaths = $deaths;
        return $this;
    }

    public function getAssists(): int
    {
        return $this->assists;
    }

    public function setAssists(int $assists): static
    {
        $this->assists = $assists;
        return $this;
    }

    public function getHeadshots(): ?int
    {
        return $this->headshots;
    }

    public function setHeadshots(?int $headshots): static
    {
        $this->headshots = $headshots;
        return $this;
    }

    public function getDamage(): ?int
    {
        return $this->damage;
    }

    public function setDamage(?int $damage): static
    {
        $this->damage = $damage;
        return $this;
    }

    public function getWeapons(): ?array
    {
        return $this->weapons;
    }

    public function setWeapons(?array $weapons): static
    {
        $this->weapons = $weapons;
        return $this;
    }

    public function getTimings(): ?array
    {
        return $this->timings;
    }

    public function setTimings(?array $timings): static
    {
        $this->timings = $timings;
        return $this;
    }

    public function getExtra(): ?array
    {
        return $this->extra;
    }

    public function setExtra(?array $extra): static
    {
        $this->extra = $extra;
        return $this;
    }
}
