<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\EventListener;

use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadEventLogRepository;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\EventListener\TimelineEventLogTrait;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerDoiBundle\Enum\DoiVerificationHistoryAction;
use MauticPlugin\LeuchtfeuerDoiBundle\Enum\DoiVerificationHistoryMetadata;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LeadTimelineSubscriber implements EventSubscriberInterface
{
    use TimelineEventLogTrait;

    public function __construct(
        Translator $translator,
        LeadEventLogRepository $leadEventLogRepository,
    ) {
        $this->translator         = $translator;
        $this->eventLogRepository = $leadEventLogRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    public function onTimelineGenerate(LeadTimelineEvent $event): void
    {
        foreach (DoiVerificationHistoryAction::cases() as $historyAction) {
            $this->addEvents(
                $event,
                $historyAction->eventType(),
                $historyAction->translationKey(),
                $historyAction->icon(),
                DoiVerificationHistoryMetadata::BUNDLE,
                DoiVerificationHistoryMetadata::OBJECT,
                $historyAction->value
            );
        }
    }
}
