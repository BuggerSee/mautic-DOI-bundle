<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Tests\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use MauticPlugin\MauticDoiBundle\Service\DoiHashContext;
use MauticPlugin\MauticDoiBundle\Service\DoiHashGenerator;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TokenGeneratorListenerFunctionalTest extends MauticMysqlTestCase
{
    public function testSendEmailWithDoiLinkToken(): void
    {
        /** @var DoiHashContext $doiHashContext */
        $doiHashContext = static::getContainer()->get(DoiHashContext::class);
        /** @var DoiHashGenerator $doiHashGenerator */
        $doiHashGenerator = static::getContainer()->get(DoiHashGenerator::class);

        $segment = new LeadList();
        $segment->setName('DOI Segment');
        $segment->setPublicName('DOI Segment');
        $segment->setAlias('doi-segment');
        $this->em->persist($segment);

        $contact = new Lead();
        $email   = 'user@example.com';
        $contact->setEmail($email);
        $this->em->persist($contact);

        $listLead = new ListLead();
        $listLead->setLead($contact);
        $listLead->setList($segment);
        $listLead->setDateAdded(new \DateTime());
        $this->em->persist($listLead);

        $emailEntity = new Email();
        $emailEntity->setDateAdded(new \DateTime());
        $emailEntity->setName('DOI Email');
        $emailEntity->setSubject('DOI Verification Email');
        $emailEntity->setEmailType('list');
        $emailEntity->setLists([$segment->getId() => $segment]);
        $emailEntity->setTemplate('Blank');
        $emailEntity->setCustomHtml('<!DOCTYPE html><html><body><p>Please verify your email:</p><a href="{doi_link}">Verify Email</a></body></html>');
        $this->em->persist($emailEntity);
        $this->em->flush();
        $this->em->clear();

        // Set context before scheduling/sending
        $doiHash = $doiHashGenerator->generate($contact->getId(), $email);
        $doiHashContext->setDoiHash($doiHash);

        $expectedFormId = 1;
        $doiHashContext->setFormId($expectedFormId);

        $this->client->request(
            Request::METHOD_POST,
            '/s/ajax?action=email:sendBatch',
            ['id' => $emailEntity->getId(), 'pending' => 1],
            [],
            $this->createAjaxHeaders()
        );

        $response = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        Assert::assertSame(
            '{"success":1,"percent":100,"progress":[1,1],"stats":{"sent":1,"failed":0,"failedRecipients":[]}}',
            $response->getContent()
        );

        $messages = $this->getMailerMessagesByToAddress('user@example.com');
        Assert::assertCount(1, $messages, 'Should have exactly one message');
        $message  = $messages[0];
        $htmlBody = $message->getHtmlBody();
        Assert::assertStringContainsString($email, $message->toString());
        Assert::assertStringContainsString('href="http', $htmlBody, 'DOI link should be present in email body');

        $doiLinkPattern = '/https?:\/\/[^\/]+\/email\/verify\/([A-Za-z0-9+\/=]+)/';
        preg_match($doiLinkPattern, $htmlBody, $matches);
        Assert::assertNotEmpty($matches, 'DOI link should match expected pattern');
        Assert::assertNotEmpty($matches[1], 'DOI token should be present');

        // Decode and verify the token contains the expected formId and hash
        $token   = $matches[1];
        $decoded = base64_decode($token, true);
        Assert::assertNotFalse($decoded, 'DOI token should be valid base64');

        $parts = explode(':', $decoded);
        Assert::assertCount(2, $parts, 'DOI token should contain formId and hash separated by colon');

        [$tokenFormId, $tokenHash] = $parts;
        Assert::assertSame((string) $expectedFormId, $tokenFormId, 'Form ID in token should match expected');
        Assert::assertSame($doiHash, $tokenHash, 'DOI hash in token should match the generated hash');
    }
}
