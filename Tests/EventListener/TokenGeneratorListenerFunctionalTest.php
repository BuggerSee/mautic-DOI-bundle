<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Tests\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TokenGeneratorListenerFunctionalTest extends MauticMysqlTestCase
{
    public function testSendEmailWithDoiLinkToken(): void
    {
        $segment = new LeadList();
        $segment->setName('DOI Segment');
        $segment->setPublicName('DOI Segment');
        $segment->setAlias('doi-segment');
        $this->em->persist($segment);

        $contacts = [];
        for ($i = 0; $i < 2; ++$i) {
            $contact = new Lead();
            $email = "user{$i}@example.com";
            $contact->setEmail($email);
            $this->em->persist($contact);

            $listLead = new ListLead();
            $listLead->setLead($contact);
            $listLead->setList($segment);
            $listLead->setDateAdded(new \DateTime());
            $this->em->persist($listLead);

            $contacts[] = $email;
        }

        $email = new Email();
        $email->setDateAdded(new \DateTime());
        $email->setName('DOI Email');
        $email->setSubject('DOI Verification Email');
        $email->setEmailType('list');
        $email->setLists([$segment->getId() => $segment]);
        $email->setTemplate('Blank');
        $email->setCustomHtml('<!DOCTYPE html><html><body><p>Please verify your email:</p><a href="{doi_link}">Verify Email</a></body></html>');
        $this->em->persist($email);
        $this->em->flush();
        $this->em->clear();

        $this->client->request(
            Request::METHOD_POST,
            '/s/ajax?action=email:sendBatch',
            ['id' => $email->getId(), 'pending' => 2],
            [],
            $this->createAjaxHeaders()
        );

        $response = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        Assert::assertSame(
            '{"success":1,"percent":100,"progress":[2,2],"stats":{"sent":2,"failed":0,"failedRecipients":[]}}',
            $response->getContent()
        );

        $messages = [
            $this->getMailerMessagesByToAddress('user0@example.com')[0],
            $this->getMailerMessagesByToAddress('user1@example.com')[0],
        ];

        $doiLinkPattern = '/https?:\/\/[^\/]+\/email\/verify\/([0-9a-z]+)/';
        $doiHashes = [];

        foreach ($messages as $i => $message) {
            $htmlBody = $message->getHtmlBody();
            Assert::assertStringContainsString("user{$i}@example.com", $message->toString());
            Assert::assertStringContainsString('href="http', $htmlBody, 'DOI link should be present in email body');
            
            preg_match($doiLinkPattern, $htmlBody, $matches);
            Assert::assertNotEmpty($matches, 'DOI link should match expected pattern');
            Assert::assertNotEmpty($matches[1], 'DOI hash should be present');
            
            $doiHashes[] = $matches[1];
        }

        Assert::assertNotEquals($doiHashes[0], $doiHashes[1], 'DOI hashes should be unique per email');
    }
}