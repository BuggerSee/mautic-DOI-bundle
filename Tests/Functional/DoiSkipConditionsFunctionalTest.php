<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\FormFixtureHelper;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class DoiSkipConditionsFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;
    private FormFixtureHelper $formFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        // Disabling rollback allows us to inspect the DB state after a failed test.
        $this->configParams['use_cleanup_rollback'] = false;
        $this->setUpSymfony();

        $pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $pluginFixtureHelper->createAndEnablePlugin();
        $this->formFixtureHelper = new FormFixtureHelper($this->em, $this->client);
    }

    /**
     * @dataProvider skipConditionsDataProvider
     *
     * @param array<int, mixed>     $skipConditions
     * @param array<string, string> $submissionData
     * @param string                $expectedStatus
     * @param int                   $contactNumber  To ensure unique emails per test
     */
    public function testSkipConditionsEvaluation(array $skipConditions, array $submissionData, string $expectedStatus, int $contactNumber): void
    {
        // 1. Setup: Create Form and DOI Config with the specific conditions
        $form = $this->formFixtureHelper->createComplexForm('Conditions Test Form');
        $this->formFixtureHelper->createDoiConfig(
            form: $form,
            skipConditions: $skipConditions
        );

        $email = "contact-{$contactNumber}@example.com";
        $submissionData['email'] = $email;

        // 2. Execution: Submit the form
        $crawler = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_conditionstestform]');
        $formElement = $formCrawler->form();

        $mauticFormValues = [];
        foreach ($submissionData as $key => $value) {
            $mauticFormValues["mauticform[{$key}]"] = $value;
        }
        $formElement->setValues($mauticFormValues);
        $this->client->submit($formElement);
        Assert::assertTrue($this->client->getResponse()->isOk());

        // 3. Assertion: Check the status of the created DOI submission
        $doiRepo = $this->em->getRepository(FormDoiSubmission::class);
        /** @var FormDoiSubmission|null $doiSubmission */
        $doiSubmission = $doiRepo->findOneBy(['email' => $email]);

        Assert::assertNotNull($doiSubmission, 'FormDoiSubmission was not created.');
        Assert::assertSame($expectedStatus, $doiSubmission->getStatus(), 'The DOI submission status is incorrect.');

        if (FormDoiSubmission::STATUS_SKIPPED === $expectedStatus) {
            Assert::assertTrue($doiSubmission->isVerificationSkipped(), 'isVerificationSkipped flag should be true.');
            Assert::assertSame(FormDoiSubmission::SKIP_REASON_CONDITION_MATCH, $doiSubmission->getSkipReason(), 'Skip reason does not match.');
            Assert::assertNotNull($doiSubmission->getDateConfirmed(), 'DateConfirmed should be set immediately on skip.');
        } else { // PENDING
            Assert::assertFalse($doiSubmission->isVerificationSkipped(), 'isVerificationSkipped flag should be false for pending submissions.');
            Assert::assertNull($doiSubmission->getSkipReason(), 'Skip reason should be null for pending submissions.');
            Assert::assertNull($doiSubmission->getDateConfirmed(), 'DateConfirmed should be null for pending submissions.');
        }
    }

    /**
     * @return \Generator<string, array<string, mixed>>
     */
    public function skipConditionsDataProvider(): \Generator
    {
        $contactNumber = 0;

        yield 'Lead country matches, should skip' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
            ],
            'submissionData' => ['country' => 'Poland'],
            'expectedStatus' => FormDoiSubmission::STATUS_SKIPPED,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Lead country does not match, should be pending' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
            ],
            'submissionData' => ['country' => 'Germany'],
            'expectedStatus' => FormDoiSubmission::STATUS_PENDING,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Company name matches (AND), should skip' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Acme Inc'], 'field' => 'companyname', 'type' => 'text', 'object' => 'company'],
            ],
            'submissionData' => ['country' => 'Poland', 'companyname' => 'Acme Inc'],
            'expectedStatus' => FormDoiSubmission::STATUS_SKIPPED,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Company name does not match (AND), should be pending' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Acme Inc'], 'field' => 'companyname', 'type' => 'text', 'object' => 'company'],
            ],
            'submissionData' => ['country' => 'Poland', 'companyname' => 'Globex Corp'],
            'expectedStatus' => FormDoiSubmission::STATUS_PENDING,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Company name matches (OR), should skip' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
                ['glue' => 'or', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Acme Inc'], 'field' => 'companyname', 'type' => 'text', 'object' => 'company'],
            ],
            'submissionData' => ['country' => 'Germany', 'companyname' => 'Acme Inc'],
            'expectedStatus' => FormDoiSubmission::STATUS_SKIPPED,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Neither condition matches (OR), should be pending' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
                ['glue' => 'or', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Acme Inc'], 'field' => 'companyname', 'type' => 'text', 'object' => 'company'],
            ],
            'submissionData' => ['country' => 'Germany', 'companyname' => 'Globex Corp'],
            'expectedStatus' => FormDoiSubmission::STATUS_PENDING,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Form-only field matches, should skip' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'B2B'], 'field' => 'source', 'type' => 'text', 'object' => 'form'],
            ],
            'submissionData' => ['country' => 'Germany', 'companyname' => 'Globex Corp', 'source' => 'B2B'],
            'expectedStatus' => FormDoiSubmission::STATUS_SKIPPED,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Form-only field does not match, should be pending' => [
            'skipConditions' => [
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'B2B'], 'field' => 'source', 'type' => 'text', 'object' => 'form'],
            ],
            'submissionData' => ['country' => 'Germany', 'companyname' => 'Globex Corp', 'source' => 'Website'],
            'expectedStatus' => FormDoiSubmission::STATUS_PENDING,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Complex mixed glue matches OR part, should skip' => [
            'skipConditions' => [
                // (country=Poland AND company=Acme) OR source=VIP
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Acme Inc'], 'field' => 'companyname', 'type' => 'text', 'object' => 'company'],
                ['glue' => 'or', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'VIP'], 'field' => 'source', 'type' => 'text', 'object' => 'form'],
            ],
            'submissionData' => ['country' => 'Germany', 'companyname' => 'Globex Corp', 'source' => 'VIP'],
            'expectedStatus' => FormDoiSubmission::STATUS_SKIPPED,
            'contactNumber'  => ++$contactNumber,
        ];

        yield 'Complex mixed glue matches AND part, should skip' => [
            'skipConditions' => [
                // (country=Poland AND company=Acme) OR source=VIP
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Poland'], 'field' => 'country', 'type' => 'country', 'object' => 'lead'],
                ['glue' => 'and', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'Acme Inc'], 'field' => 'companyname', 'type' => 'text', 'object' => 'company'],
                ['glue' => 'or', 'operator' => OperatorOptions::EQUAL_TO, 'properties' => ['filter' => 'VIP'], 'field' => 'source', 'type' => 'text', 'object' => 'form'],
            ],
            'submissionData' => ['country' => 'Poland', 'companyname' => 'Acme Inc', 'source' => 'Other'],
            'expectedStatus' => FormDoiSubmission::STATUS_SKIPPED,
            'contactNumber'  => ++$contactNumber,
        ];
    }
}