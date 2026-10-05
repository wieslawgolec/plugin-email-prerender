<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailEvent;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Auto-reset pre-render cache when email template / dynamic content changes.
 */
class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    /** Fields that affect rendered output when changed on the Email entity. */
    private const EMAIL_CONTENT_FIELDS = [
        'customHtml',
        'subject',
        'plainText',
        'dynamicContent',
        'preheaderText',
        'fromAddress',
        'fromName',
        'replyToAddress',
        'bccAddress',
        'headers',
        'template',
        'revision',
    ];

    public function __construct(
        private PrerenderModel $prerenderModel,
        private CoreParametersHelper $coreParametersHelper,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            EmailEvents::EMAIL_POST_SAVE => ['onEmailPostSave', 0],
        ];

        // Optional: Dynamic Content entity events (class may vary by Mautic minor)
        if (class_exists(\Mautic\DynamicContentBundle\DynamicContentEvents::class)) {
            $events[\Mautic\DynamicContentBundle\DynamicContentEvents::POST_SAVE] = ['onDynamicContentPostSave', 0];
        }

        return $events;
    }

    public function onEmailPostSave(EmailEvent $event): void
    {
        if (!$this->coreParametersHelper->get('emailprerender.auto_invalidate', true)) {
            return;
        }

        $email = $event->getEmail();
        if (null === $email || null === $email->getId()) {
            return;
        }

        $changes = $event->getChanges();
        if (!$this->changesAffectRendering($changes)) {
            // Still invalidate if dynamicContent key present under nested changes
            if (!$this->arrayContainsAnyKey($changes, self::EMAIL_CONTENT_FIELDS)) {
                return;
            }
        }

        $count = $this->prerenderModel->clearByEmailId((int) $email->getId());
        $this->logger->info('EmailPreRender: invalidated cache after email save', [
            'emailId' => $email->getId(),
            'rows'    => $count,
            'changes' => array_keys($changes),
        ]);
    }

    /**
     * When a standalone Dynamic Content item is saved, clear cache for emails
     * that reference it (slot name / token / ID in HTML or dynamicContent JSON).
     *
     * @param object $event DynamicContent event (duck-typed for version differences)
 */
    public function onDynamicContentPostSave(object $event): void
    {
        if (!$this->coreParametersHelper->get('emailprerender.auto_invalidate', true)) {
            return;
        }

        $dwc = method_exists($event, 'getDynamicContent') ? $event->getDynamicContent() : null;
        if (null === $dwc || !method_exists($dwc, 'getId')) {
            return;
        }

        $id   = (int) $dwc->getId();
        $name = method_exists($dwc, 'getName') ? (string) $dwc->getName() : '';
        $slot = method_exists($dwc, 'getSlotName') ? (string) $dwc->getSlotName() : '';

        $needles = array_filter([
            (string) $id,
            $name,
            $slot,
            '{dwc='.$slot.'}',
            'dwc='.$slot,
        ]);

        if ($needles === []) {
            return;
        }

        $emailIds = $this->findEmailIdsReferencing($needles);
        foreach ($emailIds as $emailId) {
            $count = $this->prerenderModel->clearByEmailId($emailId);
            $this->logger->info('EmailPreRender: invalidated cache after DWC save', [
                'dwcId'   => $id,
                'emailId' => $emailId,
                'rows'    => $count,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function changesAffectRendering(array $changes): bool
    {
        foreach (self::EMAIL_CONTENT_FIELDS as $field) {
            if (array_key_exists($field, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $haystack
     * @param list<string>         $keys
     */
    private function arrayContainsAnyKey(array $haystack, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $haystack)) {
                return true;
            }
        }
        foreach ($haystack as $value) {
            if (is_array($value) && $this->arrayContainsAnyKey($value, $keys)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $needles
     *
     * @return list<int>
     */
    private function findEmailIdsReferencing(array $needles): array
    {
        $prefix = defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '';
        $conn   = $this->em->getConnection();
        $qb     = $conn->createQueryBuilder();
        $qb->select('DISTINCT e.id')
            ->from($prefix.'emails', 'e');

        $ors = [];
        foreach ($needles as $i => $needle) {
            if ('' === $needle) {
                continue;
            }
            $param = 'n'.$i;
            $ors[] = $qb->expr()->like('e.custom_html', ':'.$param);
            $ors[] = $qb->expr()->like('e.dynamic_content', ':'.$param);
            $qb->setParameter($param, '%'.$needle.'%');
        }

        if ($ors === []) {
            return [];
        }

        $qb->where($qb->expr()->or(...$ors));

        try {
            return array_map('intval', $qb->executeQuery()->fetchFirstColumn());
        } catch (\Throwable $e) {
            $this->logger->warning('EmailPreRender: DWC email lookup failed: '.$e->getMessage());

            return [];
        }
    }
}
