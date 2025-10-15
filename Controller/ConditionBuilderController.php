<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Controller;

use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\FormBundle\Model\FormModel;
use Mautic\LeadBundle\Form\Type\FilterPropertiesType;
use Mautic\LeadBundle\Provider\FormAdjustmentsProviderInterface;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\AvailableSkipOptions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ConditionBuilderController extends AbstractController
{
    public function renderConditionAction(
        Request $request,
        FormFactoryInterface $formFactory,
        FormAdjustmentsProviderInterface $formAdjustmentsProvider,
        AvailableSkipOptions $availableSkipOptions,
        FormModel $formModel
    ): JsonResponse {
        $fieldAlias  = InputHelper::clean($request->get('fieldAlias'));
        $fieldObject = InputHelper::clean($request->get('fieldObject'));
        $operator    = InputHelper::clean($request->get('operator'));
        $search      = InputHelper::clean($request->get('search'));
        $formId      = InputHelper::clean($request->get('formId'));
        $filterNum   = (int) $request->get('filterNum');

        $formEntity = $formModel->getEntity($formId);
        $form       = $formFactory->createNamed('RENAME', FilterPropertiesType::class);

        if ($fieldAlias && $operator) {
            $formAdjustmentsProvider->adjustForm(
                $form,
                $fieldAlias,
                $fieldObject,
                $operator,
                $availableSkipOptions->getAvailableSkipOptions($formEntity, $search)[$fieldObject][$fieldAlias]
            );
        }

        $formHtml = $this->renderView(
            '@MauticLead/List/filterpropform.html.twig',
            [
                'form' => $form->createView(),
            ]
        );

        $formHtml = str_replace('id="RENAME', "id=\"mauticform_doiConfig_skipConditions_{$filterNum}_properties", $formHtml);
        $formHtml = str_replace('name="RENAME', "name=\"mauticform[doiConfig][skipConditions][{$filterNum}][properties]", $formHtml);

        return new JsonResponse(
            [
                'viewParameters' => [
                    'form' => $formHtml,
                ],
            ]
        );
    }
}
