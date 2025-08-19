<?php

namespace MauticPlugin\MauticDoiBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\FormBundle\Form\Type\ActionType;
use Mautic\FormBundle\Model\FormModel;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;
use MauticPlugin\MauticDoiBundle\Service\FormDoiActionSessionManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DoiActionController extends AbstractStandardFormController
{

    public function newAction(
        Request $request,
        FormDoiActionSessionManager $formDoiActionSessionManager,
        FormModel $formModel): JsonResponse|Response
    {
        $success = 0;
        $valid   = $cancelled   = false;
        $method  = $request->getMethod();

        if ('POST' == $method) {
            $formAction = $request->request->all()['formaction'] ?? [];
            $actionType = $formAction['type'];
            $formId     = $formAction['formId'];
        } else {
            $actionType = $request->query->get('type');
            $formId     = $request->query->get('formId');
            $formAction = [
                'type'   => $actionType,
                'formId' => $formId,
            ];
        }

        // ajax only for form fields
        if (!$actionType
            || !$request->isXmlHttpRequest()
            || !$this->security->isGranted(['form:forms:editown', 'form:forms:editother', 'form:forms:create'], 'MATCH_ONE')
        ) {
            return $this->modalAccessDenied();
        }

        // fire the form builder event
        $customComponents = $formModel->getCustomComponents();
        $form             = $this->formFactory->create(ActionType::class, $formAction, [
            'action'   => $this->generateUrl('mautic_doi_formaction_action', ['objectAction' => 'new']),
            'settings' => $customComponents['actions'][$actionType],
            'formId'   => $formId,
        ]);
        $form->get('formId')->setData($formId);
        $formAction['settings'] = $customComponents['actions'][$actionType];

        // Check for a submitted form and process it
        if ('POST' == $method) {
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $success = 1;

                    // form is valid so process the data
                    $keyId = 'new'.hash('sha1', uniqid((string)mt_rand()));

                    $formData         = $form->getData();
                    $formAction       = array_merge($formAction, $formData);
                    $formAction['id'] = $keyId;
                    if (empty($formAction['name'])) {
                        // set it to the event default
                        $formAction['name'] = $this->translator->trans($formAction['settings']['label']);
                    }
                    $formDoiActionSessionManager->updateActionInSession($formId, $keyId, $formAction);
                }
            }
        }

        $viewParams = ['type' => $actionType];

        if ($cancelled || $valid) {
            $closeModal = true;
        } else {
            $closeModal                 = false;
            $viewParams['tmpl']         = 'action';
            $viewParams['form']         = $form->createView();
            $header                     = $formAction['settings']['label'];
            $viewParams['actionHeader'] = $this->translator->trans($header);

            if (isset($formAction['settings']['formTheme'])) {
                $viewParams['formTheme'] = $formAction['settings']['formTheme'];
            }
        }

        $passthroughVars = [
            'mauticContent' => 'formDoiAction',
            'success'       => $success,
            'route'         => false,
        ];

        if (!empty($keyId)) {
            // prevent undefined errors
            $entity     = new FormDoiAction();
            $blank      = $entity->convertToArray();
            $formAction = array_merge($blank, $formAction);

            $template = (!empty($formAction['settings']['template'])) ? $formAction['settings']['template'] :
                '@MauticDoi/Action/_generic.html.twig';
            $passthroughVars['actionId']   = $keyId;
            $passthroughVars['actionHtml'] = $this->renderView($template, [
                'inForm' => true,
                'action' => $formAction,
                'id'     => $keyId,
                'formId' => $formId,
            ]);
        }

        if ($closeModal) {
            // just close the modal
            $passthroughVars['closeModal'] = 1;

            return new JsonResponse($passthroughVars);
        }

        return $this->ajaxAction($request, [
            'contentTemplate' => '@MauticDoi/Builder/'.$viewParams['tmpl'].'.html.twig',
            'viewParameters'  => $viewParams,
            'passthroughVars' => $passthroughVars,
        ]);
    }

    public function editAction(
        Request                     $request,
        string                      $objectId,
        FormDoiActionSessionManager $formDoiActionSessionManager,
        FormModel                   $formModel): JsonResponse|Response
    {
        $method     = $request->getMethod();
        $formaction = $request->request->get('formaction') ?? [];
        $formId     = 'POST' === $method ? ($formaction['formId'] ?? '') : $request->query->get('formId');
        $actions    = $formDoiActionSessionManager->getActionsFromSession($formId);
        $success    = 0;
        $valid      = $cancelled      = false;
        $formAction = array_key_exists($objectId, $actions) ? $actions[$objectId] : null;

        if (null !== $formAction) {
            $actionType             = $formAction['type'];
            $customComponents       = $formModel->getCustomComponents();
            $formAction['settings'] = $customComponents['actions'][$actionType];

            // ajax only for form fields
            if (!$actionType
                || !$request->isXmlHttpRequest()
                || !$this->security->isGranted(['form:forms:editown', 'form:forms:editother', 'form:forms:create'], 'MATCH_ONE')
            ) {
                return $this->modalAccessDenied();
            }

            $form = $this->formFactory->create(ActionType::class, $formAction, [
                'action'   => $this->generateUrl('mautic_doi_formaction_action', ['objectAction' => 'edit', 'objectId' => $objectId]),
                'settings' => $formAction['settings'],
                'formId'   => $formId,
            ]);
            $form->get('formId')->setData($formId);

            // Check for a submitted form and process it
            if ('POST' == $method) {
                if (!$cancelled = $this->isFormCancelled($form)) {
                    if ($valid = $this->isFormValid($form)) {
                        $success = 1;

                        // form is valid so process the data
                        $formData = $form->getData();
                        // overwrite with updated data
                        $formAction = array_merge($actions[$objectId], $formData);
                        if (empty($formAction['name'])) {
                            // set it to the event default
                            $formAction['name'] = $this->translator->trans($formAction['settings']['label']);
                        }
                        $formDoiActionSessionManager->updateActionInSession($formId, $objectId, $formAction);

                        // generate HTML for the field
                        $keyId = $objectId;

                        // take note if this is a submit button or not
                        if ('button' == $actionType) {
                            $submits = $request->getSession()->get('mautic.formactions.submits', []);
                            if ('submit' == $formAction['properties']['type'] && !in_array($keyId, $submits)) {
                                // button type updated to submit
                                $submits[] = $keyId;
                                $request->getSession()->set('mautic.formactions.submits', $submits);
                            } elseif ('submit' != $formAction['properties']['type'] && in_array($keyId, $submits)) {
                                // button type updated to something other than submit
                                $key = array_search($keyId, $submits);
                                unset($submits[$key]);
                                $request->getSession()->set('mautic.formactions.submits', $submits);
                            }
                        }
                    }
                }
            }

            $viewParams = ['type' => $actionType];
            if ($cancelled || $valid) {
                $closeModal = true;
            } else {
                $closeModal                 = false;
                $viewParams['tmpl']         = 'action';
                $viewParams['form']         = $form->createView();
                $viewParams['actionHeader'] = $this->translator->trans($formAction['settings']['label']);

                if (isset($formAction['settings']['formTheme'])) {
                    $viewParams['formTheme'] = $formAction['settings']['formTheme'];
                }
            }

            $passthroughVars = [
                'mauticContent' => 'formDoiAction',
                'success'       => $success,
                'route'         => false,
            ];

            if (!empty($keyId)) {
                $passthroughVars['actionId'] = $keyId;

                // prevent undefined errors
                $entity     = new FormDoiAction();
                $blank      = $entity->convertToArray();
                $formAction = array_merge($blank, $formAction);
                $template   = (!empty($formAction['settings']['template'])) ? $formAction['settings']['template'] :
                    '@MauticDoi/Action/_generic.html.twig';
                $passthroughVars['actionHtml'] = $this->renderView($template, [
                    'inForm' => true,
                    'action' => $formAction,
                    'id'     => $keyId,
                    'formId' => $formId,
                ]);
            }

            if ($closeModal) {
                // just close the modal
                $passthroughVars['closeModal'] = 1;

                return new JsonResponse($passthroughVars);
            }

            return $this->ajaxAction($request, [
                'contentTemplate' => '@MauticDoi/Builder/'.$viewParams['tmpl'].'.html.twig',
                'viewParameters'  => $viewParams,
                'passthroughVars' => $passthroughVars,
            ]);
        }

        return new JsonResponse(['success' => 0]);
    }

    public function deleteAction(Request $request, int $objectId, FormDoiActionSessionManager $formDoiActionSessionManager): JsonResponse
    {
        $formId  = $request->query->get('formId');
        $actions = $formDoiActionSessionManager->getActionsFromSession($formId);

        // ajax only for form fields
        if (!$request->isXmlHttpRequest()
            || !$this->security->isGranted(['form:forms:editown', 'form:forms:editother', 'form:forms:create'], 'MATCH_ONE')
        ) {
            return $this->accessDenied();
        }

        $formAction = (array_key_exists($objectId, $actions)) ? $actions[$objectId] : null;
        if ('POST' == $request->getMethod() && null !== $formAction) {
            // Remove the action from the session
            $formDoiActionSessionManager->removeActionFromSession($formId, $objectId);

            // take note if this is a submit button or not
            if ('button' == $formAction['type']) {
                $submits    = $request->getSession()->get('mautic.formactions.submits', []);
                $properties = $formAction['properties'];
                if ('submit' == $properties['type'] && in_array($objectId, $submits)) {
                    $key = array_search($objectId, $submits);
                    unset($submits[$key]);
                    $request->getSession()->set('mautic.formactions.submits', $submits);
                }
            }

            $dataArray = [
                'mauticContent' => 'formDoiAction',
                'success'       => 1,
                'route'         => false,
            ];
        } else {
            $dataArray = ['success' => 0];
        }

        return new JsonResponse($dataArray);
    }

    protected function getModelName(): string
    {
        return 'MauticDoiBundle:DoiAction';
    }
}
