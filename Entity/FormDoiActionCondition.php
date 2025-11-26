<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormDoiActionConditionRepository::class)]
#[ORM\Table(name: 'form_doi_actions_conditions')]
class FormDoiActionCondition
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: FormDoiAction::class)]
    #[ORM\JoinColumn(name: 'action_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private FormDoiAction $action;

    /**
     * @var array<mixed>|null
     */
    #[ORM\Column(name: 'conditions', type: 'json', nullable: true)]
    private ?array $conditions = null;

    public function getAction(): FormDoiAction
    {
        return $this->action;
    }

    public function setAction(FormDoiAction $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array<mixed>|null
     */
    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    /**
     * @param array<mixed>|null $conditions
     */
    public function setConditions(?array $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }
}
