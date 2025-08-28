<?php

namespace MauticPlugin\MauticDoiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends AbstractController
{
    public function verifyEmailAction(string $hash): Response
    {
        // TODO: Implement email verification logic

        return new Response();
    }
}
