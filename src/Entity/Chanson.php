<?php

namespace App\Entity;

use App\Repository\ChansonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;

/**
* @Hateoas\Relation(
* "self",
* href = @Hateoas\Route(
* "detailChanson",
* parameters = { "id" = "expr(object.getId())" }
* ),
* exclusion = @Hateoas\Exclusion(groups="getChanteurs")
* )
*
* @Hateoas\Relation(
* "delete",
* href = @Hateoas\Route(
* "deleteChanson",
* parameters = { "id" = "expr(object.getId())" },
* ),
* exclusion = @Hateoas\Exclusion(groups="getChanteurs", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
* )
*
* @Hateoas\Relation(
* "update",
* href = @Hateoas\Route(
* "updateChanson",
* parameters = { "id" = "expr(object.getId())" },
* ),
* exclusion = @Hateoas\Exclusion(groups="getChanteurs", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
* )
*
*/

#[ORM\Entity(repositoryClass: ChansonRepository::class)]
class Chanson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getChanteurs"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getChanteurs"])]
    #[Assert\NotBlank(message: "Le nom de la chanson est obligatoire")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le nom de la chanson doit faire au moins {{ limit }} caractères", maxMessage: "Le nom de la chanson ne peut pas faire plus de {{ limit }} caractères")]
   
    private ?string $nameChanson = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Groups(["getChanteurs"])]
    private ?\DateTimeInterface $duree = null;

    #[ORM\ManyToOne(inversedBy: 'chansons', cascade: ['persist', 'remove'])]
    private ?Disque $Disque = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameChanson(): ?string
    {
        return $this->nameChanson;
    }

    public function setNameChanson(string $nameChanson): static
    {
        $this->nameChanson = $nameChanson;

        return $this;
    }

    public function getDuree(): ?\DateTimeInterface
    {
        return $this->duree;
    }

    public function setDuree(\DateTimeInterface $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getDisque(): ?Disque
    {
        return $this->Disque;
    }

    public function setDisque(?Disque $Disque): static
    {
        $this->Disque = $Disque;

        return $this;
    }
}
