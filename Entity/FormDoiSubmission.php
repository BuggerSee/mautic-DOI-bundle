<?php

namespace MauticPlugin\MauticDoiBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Lead;

#[ORM\Entity(repositoryClass: FormDoiSubmissionRepository::class)]
#[ORM\Table(name: 'form_doi_submissions')]
#[ORM\Index(columns: ['hash'], name: 'form_doi_submission_hash_search')]
#[ORM\Index(columns: ['status'], name: 'form_doi_submission_status_search')]
class FormDoiSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Submission::class)]
    #[ORM\JoinColumn(name: 'form_submission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Submission $formSubmission = null;

    #[ORM\ManyToOne(targetEntity: Form::class)]
    #[ORM\JoinColumn(name: 'form_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Form $form = null;

    #[ORM\ManyToOne(targetEntity: Lead::class)]
    #[ORM\JoinColumn(name: 'lead_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Lead $lead = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $hash;

    #[ORM\Column(name: 'date_created', type: 'datetime')]
    private DateTime $dateCreated;

    #[ORM\Column(name: 'date_confirmed', type: 'datetime', nullable: true)]
    private ?DateTime $dateConfirmed = null;

    #[ORM\Column(name: 'date_expired', type: 'datetime', nullable: true)]
    private ?DateTime $dateExpired = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setFormSubmission(Submission $formSubmission): self
    {
        $this->formSubmission = $formSubmission;

        return $this;
    }

    public function getFormSubmission(): ?Submission
    {
        return $this->formSubmission;
    }

    public function setForm(Form $form): self
    {
        $this->form = $form;

        return $this;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setLead(?Lead $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setDateCreated(DateTime $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateCreated(): DateTime
    {
        return $this->dateCreated;
    }

    public function setDateConfirmed(?DateTime $dateConfirmed): self
    {
        $this->dateConfirmed = $dateConfirmed;

        return $this;
    }

    public function getDateConfirmed(): ?DateTime
    {
        return $this->dateConfirmed;
    }

    public function setDateExpired(?DateTime $dateExpired): self
    {
        $this->dateExpired = $dateExpired;

        return $this;
    }

    public function getDateExpired(): ?DateTime
    {
        return $this->dateExpired;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function confirm(): self
    {
        $this->status = 'confirmed';
        $this->dateConfirmed = new DateTime();

        return $this;
    }

    public function expire(): self
    {
        $this->status = 'expired';
        $this->dateExpired = new DateTime();

        return $this;
    }
}