<?php

namespace App\Entity;

use App\Repository\ChanteurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Since;

/**
* @Hateoas\Relation(
* "self",
* href = @Hateoas\Route(
* "detailChanteur",
* parameters = { "id" = "expr(object.getId())" }
* ),
* exclusion = @Hateoas\Exclusion(groups="getChanteurs")
* )
*
* @Hateoas\Relation(
* "delete",
* href = @Hateoas\Route(
* "deleteChanteur",
* parameters = { "id" = "expr(object.getId())" },
* ),
* exclusion = @Hateoas\Exclusion(groups="getChanteurs", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
* )
*
* @Hateoas\Relation(
* "update",
* href = @Hateoas\Route(
* "updateChanteur",
* parameters = { "id" = "expr(object.getId())" },
* ),
* exclusion = @Hateoas\Exclusion(groups="getChanteurs", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
* )
*
*/

#[ORM\Entity(repositoryClass: ChanteurRepository::class)]
class Chanteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getChanteurs"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getChanteurs"])]
    #[Assert\NotBlank(message: "Le nom du chanteur est obligatoire")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le nom du chanteur doit faire au moins {{ limit }} caractères", maxMessage: "Le nom du chanteur ne peut pas faire plus de {{ limit }} caractères")]
    private ?string $Name = null;

    /**
     * @var Collection<int, Disque>
     */
    #[ORM\OneToMany(targetEntity: Disque::class, mappedBy: 'chanteur', cascade: ['persist', 'remove'])]
    private Collection $disque;

    #[ORM\Column(length: 255)]
    #[Groups(["getChanteurs"])]
    #[Assert\NotBlank(message: "Le lastnom du chanteur est obligatoire")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le lastnom du chanteur doit faire au moins {{ limit }} caractères", maxMessage: "Le lastnom du chanteur ne peut pas faire plus de {{ limit }} caractères")]
    #[Since("2.0")]
    
    private ?string $lastName = null;

    public function __construct()
    {
        $this->disque = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(string $Name): static
    {
        $this->Name = $Name;

        return $this;
    }

    /**
     * @return Collection<int, Disque>
     */
    public function getDisque(): Collection
    {
        return $this->disque;
    }

    public function addDisque(Disque $disque): static
    {
        if (!$this->disque->contains($disque)) {
            $this->disque->add($disque);
            $disque->setChanteur($this);
        }

        return $this;
    }

    public function removeDisque(Disque $disque): static
    {
        if ($this->disque->removeElement($disque)) {
            // set the owning side to null (unless already changed)
            if ($disque->getChanteur() === $this) {
                $disque->setChanteur(null);
            }
        }

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }
}
