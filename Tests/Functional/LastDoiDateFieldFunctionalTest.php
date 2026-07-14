<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Command\UpdateLeadListsCommand;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Entity\ListLead;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Enum\LastDoiDateField;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\LastDoiDateFieldInstaller;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\FormFixtureHelper;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LastDoiDateFieldFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FormFixtureHelper $formFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $pluginFixtureHelper->createAndEnablePlugin();

        /** @var FieldModel $fieldModel */
        $fieldModel = static::getContainer()->get('mautic.lead.model.field');
        LastDoiDateFieldInstaller::install(
            $fieldModel,
            static::getContainer()->get('translator'),
            static::getContainer()->get('logger'),
        );

        $this->formFixtureHelper = new FormFixtureHelper($this->em, $this->client);
    }

    public function testLastDoiDateFieldIsCreatedOnInstall(): void
    {
        $field = $this->em->getRepository(LeadField::class)->findOneBy(['alias' => LastDoiDateField::ALIAS]);

        Assert::assertInstanceOf(LeadField::class, $field);
        Assert::assertSame('datetime', $field->getType());
        Assert::assertSame('lead', $field->getObject());
    }

    public function testLastDoiDateIsSetOnSuccessfulVerification(): void
    {
        $form = $this->createDoiForm('Last DOI Date Form 1');
        $this->submitDoiForm($form, 'verified@example.com');

        $doiSubmission = $this->getLatestDoiSubmission();
        $this->verifyDoiSubmission($form, $doiSubmission);

        $contact = $doiSubmission->getLead();
        Assert::assertNotNull($contact);

        $this->em->refresh($contact);
        $this->em->refresh($doiSubmission);

        Assert::assertSame('confirmed', $doiSubmission->getStatus());
        Assert::assertSame(
            $doiSubmission->getDateConfirmed()?->format('Y-m-d H:i:s'),
            $this->getContactFieldValue($contact, LastDoiDateField::ALIAS)
        );
    }

    public function testLastDoiDateIsOverwrittenOnRepeatVerification(): void
    {
        $formOne = $this->createDoiForm('Last DOI Date Form A');
        $formTwo = $this->createDoiForm('Last DOI Date Form B');

        $this->submitDoiForm($formOne, 'repeat@example.com');
        $firstSubmission = $this->getLatestDoiSubmissionForEmail('repeat@example.com');
        $this->verifyDoiSubmission($formOne, $firstSubmission);

        $contact = $firstSubmission->getLead();
        Assert::assertNotNull($contact);
        $this->em->refresh($contact);

        $firstValue = $this->getContactFieldValue($contact, LastDoiDateField::ALIAS);
        Assert::assertNotNull($firstValue);

        sleep(1);

        $this->submitDoiForm($formTwo, 'repeat@example.com');
        $secondSubmission = $this->getLatestDoiSubmissionForEmail('repeat@example.com');
        $this->verifyDoiSubmission($formTwo, $secondSubmission);

        $this->em->refresh($contact);
        $this->em->refresh($secondSubmission);

        $secondValue = $this->getContactFieldValue($contact, LastDoiDateField::ALIAS);
        Assert::assertNotNull($secondValue);
        Assert::assertSame($secondSubmission->getDateConfirmed()?->format('Y-m-d H:i:s'), $secondValue);
        Assert::assertGreaterThan($firstValue, $secondValue);
    }

    public function testLastDoiDateCanBeUsedAsSegmentFilter(): void
    {
        $verifiedForm   = $this->createDoiForm('Segment Verified Form');
        $unverifiedForm = $this->createDoiForm('Segment Unverified Form');

        $this->submitDoiForm($verifiedForm, 'segment-verified@example.com');
        $verifiedSubmission = $this->getLatestDoiSubmissionForEmail('segment-verified@example.com');
        $this->verifyDoiSubmission($verifiedForm, $verifiedSubmission);

        $this->submitDoiForm($unverifiedForm, 'segment-unverified@example.com');

        $segment = new LeadList();
        $segment->setName('Contacts with last DOI date');
        $segment->setAlias('contacts-with-last-doi-date');
        $segment->setPublicName('Contacts with last DOI date');
        $segment->setFilters([
            [
                'glue'     => 'and',
                'field'    => LastDoiDateField::ALIAS,
                'object'   => 'lead',
                'type'     => 'datetime',
                'filter'   => null,
                'display'  => null,
                'operator' => '!empty',
            ],
        ]);
        $this->em->persist($segment);
        $this->em->flush();
        $segmentId = $segment->getId();

        $this->em->clear();

        $output = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['-i' => $segmentId, '--env' => 'test']);
        Assert::assertSame(0, $output->getStatusCode());

        $segment = $this->em->getRepository(LeadList::class)->find($segmentId);
        Assert::assertInstanceOf(LeadList::class, $segment);

        $verifiedSubmission = $this->em->getRepository(FormDoiSubmission::class)->findOneBy(['email' => 'segment-verified@example.com']);
        Assert::assertInstanceOf(FormDoiSubmission::class, $verifiedSubmission);

        $verifiedContact = $verifiedSubmission->getLead();
        Assert::assertNotNull($verifiedContact);

        $listLeadRepository = $this->em->getRepository(ListLead::class);
        Assert::assertNotNull($listLeadRepository->findOneBy([
            'list' => $segment,
            'lead' => $verifiedContact,
        ]));

        $unverifiedSubmission = $this->em->getRepository(FormDoiSubmission::class)->findOneBy(['email' => 'segment-unverified@example.com']);
        Assert::assertNotNull($unverifiedSubmission);
        $unverifiedContact = $unverifiedSubmission->getLead();
        Assert::assertNotNull($unverifiedContact);

        Assert::assertNull($listLeadRepository->findOneBy([
            'list' => $segment,
            'lead' => $unverifiedContact,
        ]));

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->em->getRepository(LeadList::class);
        Assert::assertSame(1, (int) $leadListRepository->getLeadCount($segment->getId()));
    }

    private function createDoiForm(string $name): Form
    {
        $form = $this->formFixtureHelper->createFormViaApi($name);
        $this->formFixtureHelper->createDoiConfig($form);

        return $form;
    }

    /**
     * @param array<string, string> $values
     */
    private function submitDoiForm(Form $form, string $email, array $values = []): void
    {
        $formNameForId = strtolower(str_replace(' ', '', $form->getName()));

        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter("form[id=mauticform_{$formNameForId}]");
        $formElement = $formCrawler->form();
        $formElement->setValues(array_merge(['mauticform[email]' => $email], $values));
        $this->client->submit($formElement);

        Assert::assertTrue($this->client->getResponse()->isOk());
    }

    private function getLatestDoiSubmissionForEmail(string $email): FormDoiSubmission
    {
        $doiSubmissions = $this->em->getRepository(FormDoiSubmission::class)->findBy(
            ['email' => $email],
            ['id' => 'DESC'],
            1
        );
        Assert::assertCount(1, $doiSubmissions);

        return $doiSubmissions[0];
    }

    private function getLatestDoiSubmission(): FormDoiSubmission
    {
        $doiSubmissions = $this->em->getRepository(FormDoiSubmission::class)->findBy([], ['id' => 'DESC'], 1);
        Assert::assertCount(1, $doiSubmissions);

        return $doiSubmissions[0];
    }

    private function verifyDoiSubmission(Form $form, FormDoiSubmission $doiSubmission): void
    {
        $token = base64_encode(sprintf('%d:%s', $form->getId(), $doiSubmission->getHash()));
        $this->client->request(Request::METHOD_GET, "/email/verify/{$token}");

        Assert::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    private function getContactFieldValue(Lead $contact, string $alias): ?string
    {
        $fields = $contact->getFields();
        foreach ($fields as $group) {
            if (isset($group[$alias]['value'])) {
                $value = $group[$alias]['value'];

                return is_string($value) ? $value : null;
            }
        }

        $this->em->refresh($contact);
        $fields = $contact->getFields();
        foreach ($fields as $group) {
            if (isset($group[$alias]['value'])) {
                $value = $group[$alias]['value'];

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }
}
