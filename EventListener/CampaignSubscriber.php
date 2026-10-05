<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Form\Type\EmailSendType;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailPreRenderBundle\EmailPreRenderEvents;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers campaign action "Pre-render email" (same form UI as email.send)
 * and executes full one-time generation into the pre-render cache.
 *
 * Place this action before "Send email" in the campaign builder.
 * Timing / delay / schedule options come from the standard campaign event
 * scheduler (same as any other action including email.send).
 */
class CampaignSubscriber implements EventSubscriberInterface
{
    public const ACTION_TYPE = 'email.prerender';

    public function __construct(
        private PrerenderModel $prerenderModel,
        private EmailModel $emailModel,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD                 => ['onCampaignBuild', 0],
            EmailPreRenderEvents::ON_CAMPAIGN_BATCH_ACTION   => ['onCampaignBatchAction', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        if (!$this->coreParametersHelper->get('emailprerender.campaign_action_enabled', true)) {
            return;
        }

        $event->addAction(
            self::ACTION_TYPE,
            [
                'label'           => 'emailprerender.campaign.event.prerender',
                'description'     => 'emailprerender.campaign.event.prerender_descr',
                'batchEventName'  => EmailPreRenderEvents::ON_CAMPAIGN_BATCH_ACTION,
                'formType'        => EmailSendType::class,
                'formTypeOptions' => ['update_select' => 'campaignevent_properties_email'],
                'formTheme'       => '@MauticEmail/FormTheme/EmailSendList/emailsend_list_row.html.twig',
                'channel'         => 'email',
                'channelIdField'  => 'email',
            ]
        );
    }

    public function onCampaignBatchAction(PendingEvent $event): void
    {
        if (!$event->checkContext(self::ACTION_TYPE)) {
            return;
        }

        $config  = $event->getConfig();
        $emailId = (int) ($config['email'] ?? 0);

        if ($emailId <= 0) {
            foreach ($event->getPending() as $log) {
                $event->passWithError($log, 'No email configured for pre-render action');
            }

            return;
        }

        /** @var Email|null $email */
        $email = $this->emailModel->getEntity($emailId);
        if (null === $email) {
            foreach ($event->getPending() as $log) {
                $event->passWithError($log, sprintf('Email ID %d not found', $emailId));
            }

            return;
        }

        $ttlHours  = (int) $this->coreParametersHelper->get('emailprerender.default_ttl_hours', 72);
        $expiresAt = $ttlHours > 0
            ? (new \DateTimeImmutable())->modify(sprintf('+%d hours', $ttlHours))
            : null;

        $contacts = $event->getContactsKeyedById();

        foreach ($contacts as $contactId => $contact) {
            $log = $event->findLogByContactId((int) $contactId);

            if (!$contact instanceof Lead) {
                $event->passWithError($log, 'Invalid contact');
                continue;
            }

            if (!$contact->getEmail()) {
                $event->passWithError($log, 'Contact has no email address');
                continue;
            }

            try {
                $ok = $this->prerenderModel->prerenderForContact($email, $contact, $expiresAt);
                if ($ok) {
                    $event->pass($log);
                } else {
                    $event->passWithError($log, 'Pre-render failed (see logs)');
                }
            } catch (\Throwable $e) {
                $this->logger->error('EmailPreRender campaign action failed: '.$e->getMessage(), [
                    'exception' => $e,
                    'contactId' => $contactId,
                    'emailId'   => $emailId,
                ]);
                $event->passWithError($log, $e->getMessage());
            }
        }
    }
}
