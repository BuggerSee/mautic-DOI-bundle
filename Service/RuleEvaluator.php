<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Exception\PrimaryCompanyNotFoundException;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmissionRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service to evaluate skip rules for DOI verification.
 */
class RuleEvaluator
{
    public const COOKIE_NAME = 'mautic_doi_receipt';

    public function __construct(
        private FormDoiSubmissionRepository $submissionRepository,
        private RequestStack $requestStack,
        private CompanyLeadRepository $companyLeadRepository
    ) {
    }

    /**
     * Evaluates if DOI verification should be skipped based on browser cookie.
     *
     * @param FormDoiConfig $config The DOI configuration for the form
     * @param string        $email  The email address being submitted
     *
     * @return bool True if verification should be skipped, false otherwise
     */
    public function shouldSkipBasedOnCookie(FormDoiConfig $config, string $email): bool
    {
        if (!$config->isSkipOnCookie()) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        $browserProofToken = $request->cookies->get(self::COOKIE_NAME);
        if (!$browserProofToken) {
            return false;
        }

        $submission = $this->submissionRepository->findOneBy(['browserProofToken' => $browserProofToken]);
        if (!$submission instanceof FormDoiSubmission) {
            return false;
        }

        if (!$submission->isConfirmed()) {
            return false;
        }

        // Validate the email address matches
        if ($submission->getEmail() !== $email) {
            return false;
        }

        return true;
    }

    public function shouldSkipBasedOnConditions(FormDoiConfig $config, Submission $submission, Lead $contact): bool
    {
        // example: [{"glue":"and","operator":"endsWith","properties":{"filter":"example.com"},"field":"email","type":"email","object":"lead"},{"glue":"or","operator":"in","properties":{"filter":["Poland","Ukraine"]},"field":"country","type":"country","object":"lead"},{"glue":"and","operator":"=","properties":{"filter":"PB"},"field":"companyname","type":"text","object":"company"},{"glue":"or","operator":"=","properties":{"filter":"black"},"field":"color","type":"select","object":"form"}]
        $skipConditions = $config->getSkipConditions();

        // example: {"id":"36","are_you":0,"title":null,"firstname":"ana","lastname":null,"company":"pb","position":null,"email":"ana@example.com","mobile":null,"phone":null,"points":36,"fax":null,"address1":null,"address2":null,"city":null,"state":null,"zipcode":null,"country":"Albania","preferred_locale":null,"timezone":null,"last_active":"2025-10-16 08:33:18","attribution_date":null,"attribution":null,"website":null,"facebook":null,"foursquare":null,"instagram":null,"linkedin":null,"skype":null,"twitter":null}
        $leadArray = $contact->getProfileFields();

        // example: {"company_id":"2","date_associated":"2025-10-16 08:17:53","is_primary":"1","id":"2","owner_id":null,"is_published":"1","date_added":"2025-10-16 08:17:40","created_by":"1","created_by_user":"Admin Mautic","date_modified":null,"modified_by":null,"modified_by_user":null,"checked_out":"2025-10-16 08:17:40","checked_out_by":"1","checked_out_by_user":"Admin Mautic","social_cache":"a:0:{}","score":"0","companyemail":null,"companyaddress1":null,"companyaddress2":null,"companyphone":null,"companycity":null,"companystate":null,"companyzipcode":null,"companycountry":null,"companyname":"pb","companywebsite":null,"companyindustry":null,"companydescription":null,"companynumber_of_employees":null,"companyfax":null,"companyannual_revenue":null}
        try {
            $companyArray = $this->companyLeadRepository->getPrimaryCompanyByLeadId($contact->getId());
        } catch (PrimaryCompanyNotFoundException) {
            $companyArray = null;
        }

        // example: {"email":"ana@example.com","country":"Albania","color":"white","f_name":"ana","consent_group":"email_consent","radio":"a","date":"2025-10-16","multi":"u"}
        $formArray = $submission->getResults();

        if (empty($skipConditions)) {
            return false;
        }

        $dataSources = [
            'lead'    => $leadArray,
            'company' => $companyArray,
            'form'    => $formArray,
        ];

        $finalResult = false;
        $isFirstRule = true;

        foreach ($skipConditions as $rule) {
            $objectType = $rule['object'] ?? null;
            $dataSource = $dataSources[$objectType] ?? null;

            $currentResult = false; // Default to false if a rule cannot be evaluated
            if (null !== $dataSource && isset($rule['field'], $rule['operator'])) {
                $field       = $rule['field'];
                $actualValue = $dataSource[$field] ?? null;
                $filterValue = $rule['properties']['filter'] ?? null;
                $operator    = $rule['operator'];

                $currentResult = $this->evaluateSingleCondition($operator, $actualValue, $filterValue);
            }

            if ($isFirstRule) {
                $finalResult = $currentResult;
                $isFirstRule = false;
            } else {
                $glue = $rule['glue'] ?? 'and'; // Default to 'and'
                if ('and' === $glue) {
                    $finalResult = $finalResult && $currentResult;
                } elseif ('or' === $glue) {
                    $finalResult = $finalResult || $currentResult;
                }
            }
        }

        return $finalResult;
    }

    /**
     * Evaluates a single condition.
     *
     * @param string $operator    The comparison operator
     * @param mixed  $actualValue The value from the contact/company/form
     * @param mixed  $filterValue The value from the rule to compare against
     */
    private function evaluateSingleCondition(string $operator, mixed $actualValue, mixed $filterValue): bool
    {
        switch ($operator) {
            case OperatorOptions::EQUAL_TO:
                return 0 === strcasecmp((string) $actualValue, (string) $filterValue);

            case OperatorOptions::NOT_EQUAL_TO:
                return 0 !== strcasecmp((string) $actualValue, (string) $filterValue);

            case OperatorOptions::GREATER_THAN:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue > (float) $filterValue;

            case OperatorOptions::GREATER_THAN_OR_EQUAL:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue >= (float) $filterValue;

            case OperatorOptions::LESS_THAN:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue < (float) $filterValue;

            case OperatorOptions::LESS_THAN_OR_EQUAL:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue <= (float) $filterValue;

            case OperatorOptions::EMPTY:
                return empty($actualValue);

            case OperatorOptions::NOT_EMPTY:
                return !empty($actualValue);

            case OperatorOptions::IN:
                if (!is_array($filterValue)) {
                    return false;
                }
                // Handle single values and comma-separated string values (from checkboxgrp)
                $actualValues = is_array($actualValue) ? $actualValue : explode(',', (string) $actualValue);

                // Normalize both arrays for case-insensitive comparison
                $actualValuesLower = array_map('strtolower', array_map('trim', $actualValues));
                $filterValuesLower = array_map('strtolower', array_map('trim', $filterValue));

                // Return true if any of the actual values are in the filter list
                return !empty(array_intersect($actualValuesLower, $filterValuesLower));

            case OperatorOptions::NOT_IN:
                if (!is_array($filterValue)) {
                    return true;
                }
                // Handle single values and comma-separated string values
                $actualValues = is_array($actualValue) ? $actualValue : explode(',', (string) $actualValue);

                $actualValuesLower = array_map('strtolower', array_map('trim', $actualValues));
                $filterValuesLower = array_map('strtolower', array_map('trim', $filterValue));

                // Return true if NONE of the actual values are in the filter list
                return empty(array_intersect($actualValuesLower, $filterValuesLower));

            case OperatorOptions::REGEXP:
                $pattern = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $pattern && 1 === @preg_match($pattern, $subject);

            case OperatorOptions::NOT_REGEXP:
                $pattern = (string) $filterValue;
                if ('' === $pattern) {
                    return true; // Does not match an empty (invalid) pattern
                }
                $subject = (string) $actualValue;

                return 1 !== @preg_match($pattern, $subject);

            case OperatorOptions::STARTS_WITH:
                $search  = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $search && str_starts_with(strtolower($subject), strtolower($search));

            case OperatorOptions::ENDS_WITH:
                $search  = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $search && str_ends_with(strtolower($subject), strtolower($search));

            case OperatorOptions::CONTAINS:
                $search  = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $search && false !== stripos($subject, $search);

            case OperatorOptions::LIKE:
                $filterString = (string) $filterValue;
                $actualString = (string) $actualValue;
                $pattern      = '/^'.str_replace('%', '.*', preg_quote($filterString, '/')).'$/i';

                return (bool) @preg_match($pattern, $actualString);

            case OperatorOptions::NOT_LIKE:
                $filterString = (string) $filterValue;
                $actualString = (string) $actualValue;
                $pattern      = '/^'.str_replace('%', '.*', preg_quote($filterString, '/')).'$/i';

                return !@preg_match($pattern, $actualString);

            default:
                return false;
        }
    }
}
