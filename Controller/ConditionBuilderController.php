<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Controller;

use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Form\Type\FilterPropertiesType;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Provider\FormAdjustmentsProviderInterface;
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
        ListModel $listModel
    ): JsonResponse {
        $fieldAlias  = InputHelper::clean($request->get('fieldAlias'));
        $fieldObject = InputHelper::clean($request->get('fieldObject'));
        $operator    = InputHelper::clean($request->get('operator'));
        $search      = InputHelper::clean($request->get('search'));
        $filterNum   = (int) $request->get('filterNum');

        $form = $formFactory->createNamed('RENAME', FilterPropertiesType::class);

        if ($fieldAlias && $operator) {
            $formAdjustmentsProvider->adjustForm(
                $form,
                $fieldAlias,
                $fieldObject,
                $operator,
                $listModel->getChoiceFields($search)[$fieldObject][$fieldAlias]
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
