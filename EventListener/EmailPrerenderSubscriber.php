<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Late listener that injects a pre-rendered payload when available.
 *
 * Priority is intentionally very low (-100) so that Advanced Templates Bundle,
 * core listeners (preheader, tokens, DWC, tracking, etc.) and any other plugins
 * run first during a normal generation. On a cache hit we only replace the
 * already-processed content.
 */
class EmailPrerenderSubscriber implements EventSubscriberInterface\n{
    public function __construct(
        private PrerenderModel $prerenderModel,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND => ['onEmailSendUseCache', -100],
            // Uncomment if you also want browser previews to benefit from cache:
            // EmailEvents::EMAIL_ON_DISPLAY => ['onEmailSendUseCache', -100],
        ];
    }

    public function onEmailSendUseCache(EmailSendEvent $event): void
    {
        // Never interfere with internal / test sends
        if ($event->isInternalSend()) {
            return;
        }

        $email = $event->getEmail();
        $lead  = $event->getLead();

        if (null === $email || null === $lead) {
            return;
        }

        $contactId = is_array($lead) ? (int) ($lead['id'] ?? 0) : (int) $lead->getId();
        if ($contactId <= 0) {
            return;
        }

        $cache = $this->prerenderModel->findCache($email, $lead);

        if (null === $cache) {
            return; // cache miss → normal pipeline continues (already ran)
        }

        // Inject the fully rendered payload that was produced earlier
        // by the complete listener chain (including Advanced Templates).
        $event->setContent($cache->getHtml());
        $event->setSubject($cache->getSubject());

        if ($cache->getPlainText()) {
            $event->setPlainText($cache->getPlainText());
        }

        $this->logger->debug(
            'EmailPreRender: cache hit for email {emailId} / contact {contactId}',
            ['emailId' => $email->getId(), 'contactId' => $contactId]
        );
    }
}
