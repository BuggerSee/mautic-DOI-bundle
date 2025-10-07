<?php

declare(strict_types=1);

return [
    'name'        => 'Email Verification and Double Opt-In (DOI) by Leuchtfeuer',
    'description' => 'Universal email verification for Mautic, including Double Opt-In (DOI)',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'routes'      => [
        'main' => [
            'mautic_doi_formaction_action' => [
                'path'       => '/forms-doi/action/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerDoiBundle\Controller\DoiActionController::executeAction',
            ],
        ],
        'public' => [
            'mautic_doi_email_verify_action' => [
                'path'       => '/email/verify/{token}',
                'controller' => 'MauticPlugin\LeuchtfeuerDoiBundle\Controller\PublicController::verifyEmailAction',
            ],
        ],
    ],
];
