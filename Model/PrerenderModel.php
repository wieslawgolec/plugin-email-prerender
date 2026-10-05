<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCache;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCacheRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handles cache lookup, storage and the heavy lifting of driving MailHelper
 * through the full generation pipeline during pre-render.
 */
class PrerenderModel
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailHelper $mailHelper,
        private EventDispatcherInterface $dispatcher,
        private LeadModel $leadModel,
        private EmailModel $emailModel,
        private LoggerInterface $logger,
    ) {
    }

    public function getRepository(): EmailPrerenderCacheRepository
    {
        return $this->em->getRepository(EmailPrerenderCache::class);
    }

    /**
     * Build a stable content fingerprint for the email template.
     */
    public function buildContentHash(Email $email): string
    {
        $parts = [
            (string) $email->getId(),
            (string) $email->getRevision(),
            md5((string) $email->getCustomHtml()),
            md5((string) $email->getSubject()),
            md5((string) $email->getPlainText()),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Build a fingerprint of contact data that can affect rendering.
     * Adjust the list of fields according to what your emails actually use.
     *
     * @param Lead|array $lead
     */
    public function buildContactHash($lead): string
    {
        if ($lead instanceof Lead) {
            $fields = $lead->getProfileFields();
        } else {
            $fields = $lead;
        }

        // Include common personalization fields + a generic dump for safety.
        // You can narrow this list for better cache hit rates.
        ksort($fields);
        $relevant = json_encode($fields);

        return hash('sha256', (string) $relevant);
    }

    /**
     * @param Lead|array $lead
     */
    public function findCache(Email $email, $lead): ?EmailPrerenderCache
    {
        $contactId = $lead instanceof Lead ? (int) $lead->getId() : (int) ($lead['id'] ?? 0);
        if ($contactId <= 0) {
            return null;
        }

        return $this->getRepository()->findValidCache(
            (int) $email->getId(),
            $contactId,
            $this->buildContentHash($email),
            $this->buildContactHash($lead)
        );
    }

    /**
     * Drive the full generation pipeline for one contact and store the result.
     *
     * IMPORTANT: This intentionally lets every EMAIL_ON_SEND listener
     * (Advanced Templates, core, other plugins) run so the cached payload
     * is identical to what a real send would produce.
     */
    public function prerenderForContact(Email $email, Lead $lead, ?\DateTimeInterface $expiresAt = null): bool
    {
        try {
            // Reset / configure MailHelper for this contact.
            // Exact public API may vary slightly across Mautic 7.x minor versions;
            // adjust if your installed version exposes different helpers.
            $this->mailHelper->reset();
            $this->mailHelper->setEmail($email);
            $this->mailHelper->setLead($lead->getProfileFields());
            $this->mailHelper->setIdHash();

            // Trigger the same generation path used on real sends.
            // Using send() with a dry-run / internal flag is safer in some versions;
            // here we rely on the event listeners being fired while building the body.
            $this->mailHelper->setSource(['emailprerender', $email->getId()]);

            // Force content generation (this will dispatch EMAIL_ON_SEND listeners).
            $this->mailHelper->addTo($lead->getEmail());
            $success = $this->mailHelper->send(false, true); // queue = false, drop = true (do not actually deliver)

            if (!$success) {
                $this->logger->warning('EmailPreRender: MailHelper reported failure for contact '.$lead->getId());

                return false;
            }

            $html    = $this->mailHelper->getBody();
            $subject = $this->mailHelper->getSubject();
            $plain   = $this->mailHelper->getPlainText();

            if (empty($html)) {
                $this->logger->warning('EmailPreRender: empty HTML after generation for contact '.$lead->getId());

                return false;
            }

            $cache = new EmailPrerenderCache();
            $cache->setEmailId((int) $email->getId())
                ->setContactId((int) $lead->getId())
                ->setContentHash($this->buildContentHash($email))
                ->setContactHash($this->buildContactHash($lead))
                ->setSubject((string) $subject)
                ->setHtml((string) $html)
                ->setPlainText($plain ?: null)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setExpiresAt($expiresAt);

            // Upsert-style: remove previous entry for same key then persist
            $existing = $this->getRepository()->findValidCache(
                $cache->getEmailId(),
                $cache->getContactId(),
                $cache->getContentHash(),
                $cache->getContactHash()
            );

            if ($existing) {
                $this->em->remove($existing);
                $this->em->flush();
            }

            $this->em->persist($cache);
            $this->em->flush();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('EmailPreRender: exception while pre-rendering: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return false;
        } finally {
            $this->mailHelper->reset();
        }
    }

    public function clearByEmailId(int $emailId): int
    {
        return $this->getRepository()->deleteByEmailId($emailId);
    }

    public function clearAll(): int
    {
        return $this->getRepository()->deleteAll();
    }

    public function clearExpired(): int
    {
        return $this->getRepository()->deleteExpired();
    }
}
