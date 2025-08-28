<?php

declare(strict_types=1);

return [
    'name'        => 'Email Verification and Double Opt-In (DOI) by Leuchtfeuer',
    'description' => 'Universal email verification for Mautic, including Double Opt-In (DOI)',
    'version'     => '0.0.3',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'routes'      => [
        'main' => [
            'mautic_doi_formaction_action' => [
                'path'       => '/forms-doi/action/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\MauticDoiBundle\Controller\DoiActionController::executeAction',
            ],
        ],
        'public' => [
            'mautic_doi_email_verify_action' => [
                'path'       => '/email/verify/{hash}',
                'controller' => 'MauticPlugin\MauticDoiBundle\Controller\PublicController::verifyEmailAction',
            ],
        ],
    ],
];
