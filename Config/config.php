<?php

declare(strict_types=1);

return [
    'name'        => 'Email Verification and Double Opt-In (DOI) by Leuchtfeuer',
    'description' => 'Universal email verification for Mautic, including Double Opt-In (DOI)',
    'version'     => '0.0.2',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'routes' => [
        'main' => [
            'mautic_doi_formaction_action' => [
                'path'       => '/forms-doi/action/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\MauticDoiBundle\Controller\DoiActionController::executeAction',
            ],
        ]
    ]
];