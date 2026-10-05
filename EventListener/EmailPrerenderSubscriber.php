<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Early EMAIL_ON_SEND listener: on valid cache hit, inject stored payload and
 * optionally short-circuit remaining listeners (configurable).
 *
 * Priority 255 = run before typical plugin/core content mutators so generation
 * is not paid twice when short_circuit is enabled.
 */
class EmailPrerenderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PrerenderModel $prerenderModel,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND => ['onEmailSendUseCache', 255],
        ];
    }

    public function onEmailSendUseCache(EmailSendEvent $event): void
    {
        if (!$this->coreParametersHelper->get('emailprerender.enabled', true)) {
            return;
        }

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
            return;
        }

        // Inject pre-generated payload (produced once during prerender)
        $event->setContent($cache->getHtml());
        $event->setSubject($cache->getSubject());

        if ($cache->getPlainText()) {
            $event->setPlainText($cache->getPlainText());
        }

        $tokens = $cache->getTokens();
        if ($tokens !== []) {
            $event->addTokens($tokens);
        }

        $this->logger->debug('EmailPreRender: cache hit email={emailId} contact={contactId}', [
            'emailId'   => $email->getId(),
            'contactId' => $contactId,
        ]);

        if ($this->coreParametersHelper->get('emailprerender.short_circuit', true)) {
            // Skip remaining EMAIL_ON_SEND listeners — content is already final.
            // Disable via emailprerender.short_circuit=false if a 3rd-party plugin
            // must still run on every real send.
            $event->stopPropagation();
        }
    }
}
