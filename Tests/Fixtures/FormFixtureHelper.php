<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiAction;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class FormFixtureHelper
{
    public function __construct(
        private EntityManagerInterface $em,
        private KernelBrowser $client
    ) {
    }

    public function createForm(string $name, string $alias): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setAlias($alias);
        $form->setPostActionProperty('Success');
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    public function createDoiConfig(
        Form $form,
        ?Email $verificationEmail = null,
        ?Email $followUpEmail = null,
        bool $enabled = true,
        ?string $successRedirectUrl = null,
        ?string $errorRedirectUrl = null
    ): FormDoiConfig {
        $config = new FormDoiConfig();
        $config->setForm($form);
        $config->setVerificationEmail($verificationEmail);
        $config->setFollowUpEmail($followUpEmail);
        $config->setEnabled($enabled);
        $config->setSuccessRedirectUrl($successRedirectUrl);
        $config->setErrorRedirectUrl($errorRedirectUrl);

        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    /**
     * @param array<string,mixed> $properties
     */
    public function createDoiAction(Form $form, string $name, string $type, array $properties): FormDoiAction
    {
        $action = new FormDoiAction();
        $action->setForm($form);
        $action->setName($name);
        $action->setType($type);
        $action->setProperties($properties);
        $action->setOrder(1);

        $this->em->persist($action);
        $this->em->flush();

        return $action;
    }

    public function createSegment(string $name, string $alias): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        $segment->setAlias($alias);
        $segment->setPublicName($name);
        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }

    public function createEmail(string $name, string $content): Email
    {
        $email = new Email();
        $email->setName($name);
        $email->setSubject($name);
        $email->setCustomHtml($content);
        $email->setEmailType('template');
        $email->setIsPublished(true);
        $this->em->persist($email);
        $this->em->flush();

        return $email;
    }

    public function createContact(string $email): Lead
    {
        $contact = new Lead();
        $contact->setEmail($email);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    public function createDoiSubmission(Form $form, string $email, \DateTime $dateSubmitted): FormDoiSubmission
    {
        $lead = $this->createContact($email);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDateSubmitted($dateSubmitted);
        $submission->setReferer('https://example.com/');
        $submission->setLead($lead);
        $this->em->persist($submission);

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission->setForm($form);
        $doiSubmission->setFormSubmission($submission);
        $doiSubmission->setLead($lead);
        $doiSubmission->setEmail($email);
        $doiSubmission->setStatus('pending');
        $doiSubmission->setHash(hash('sha256', $email.time()));
        $doiSubmission->setDateCreated($dateSubmitted);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }

    public function createFormViaApi(string $name): Form
    {
        $formPayload = [
            'name'        => $name,
            'description' => '',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'        => 'Email',
                    'type'         => 'email',
                    'alias'        => 'email',
                    'leadField'    => 'email',
                    'mappedField'  => 'email',
                    'mappedObject' => 'contact',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction'  => 'return',
        ];

        $this->client->request('POST', '/api/forms/new', $formPayload);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $formId   = $response['form']['id'];

        return $this->em->getRepository(Form::class)->find($formId);
    }

    public function createFormWithCompanyViaApi(string $name): Form
    {
        $formPayload = [
            'name'        => $name,
            'description' => '',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'        => 'Email',
                    'type'         => 'email',
                    'alias'        => 'email',
                    'leadField'    => 'email',
                    'mappedField'  => 'email',
                    'mappedObject' => 'contact',
                ],
                [
                    'label'        => 'Company',
                    'type'         => 'text',
                    'alias'        => 'company',
                    'leadField'    => 'companyname',
                    'mappedField'  => 'companyname',
                    'mappedObject' => 'company',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction' => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = $response['form']['id'];
        $repository     = $this->em->getRepository(Form::class);

        return $repository->find($formId);
    }
}
