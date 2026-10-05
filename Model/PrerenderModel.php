<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCache;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCacheRepository;
use Psr\Log\LoggerInterface;

/**
 * Cache storage + Mautic 7-compatible full generation via MailHelper::dispatchSendEvent().
 * Does not deliver mail; only builds the same payload the real send path would build.
 */
class PrerenderModel
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailHelper $mailHelper,
        private LoggerInterface $logger,
    ) {
    }

    public function getRepository(): EmailPrerenderCacheRepository
    {
        /** @var EmailPrerenderCacheRepository $repo */
        $repo = $this->em->getRepository(EmailPrerenderCache::class);

        return $repo;
    }

    public function buildContentHash(Email $email): string
    {
        $revision = method_exists($email, 'getRevision') ? (string) $email->getRevision() : '';
        $parts    = [
            (string) $email->getId(),
            $revision,
            md5((string) $email->getCustomHtml()),
            md5((string) $email->getSubject()),
            md5((string) $email->getPlainText()),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param Lead|array<string, mixed> $lead
     */
    public function buildContactHash($lead): string
    {
        if ($lead instanceof Lead) {
            $fields = $lead->getProfileFields();
        } else {
            $fields = $lead;
        }

        ksort($fields);

        return hash('sha256', (string) json_encode($fields));
    }

    /**
     * @param Lead|array<string, mixed> $lead
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
     * Run the full EMAIL_ON_SEND pipeline once and store the result.
     * Compatible with Mautic 7.x MailHelper public API:
     * reset, setEmail, setLead, setIdHash, setSource, setBody, setSubject,
     * setPlainText, dispatchSendEvent, getBody, getSubject, getPlainText, getTokens.
     */
    public function prerenderForContact(Email $email, Lead $lead, ?\DateTimeInterface $expiresAt = null): bool
    {
        if (!$lead->getEmail()) {
            return false;
        }

        try {
            $this->mailHelper->reset(true);
            $this->mailHelper->setEmail($email);
            $this->mailHelper->setLead($lead->getProfileFields());
            $this->mailHelper->setIdHash();
            $this->mailHelper->setSource(['emailprerender', $email->getId()]);

            // Seed from entity so listeners start from the same baseline as a real send
            $html = (string) $email->getCustomHtml();
            $this->mailHelper->setBody($html, 'text/html', null, true);
            $this->mailHelper->setSubject((string) $email->getSubject());

            $plain = $email->getPlainText();
            if ($plain) {
                $this->mailHelper->setPlainText($plain);
            }

            // Full token / Twig / DWC / plugin pipeline — no transport delivery
            $this->mailHelper->dispatchSendEvent();

            $finalHtml    = (string) $this->mailHelper->getBody();
            $finalSubject = (string) $this->mailHelper->getSubject();
            $finalPlain   = $this->mailHelper->getPlainText();
            $tokens       = method_exists($this->mailHelper, 'getTokens')
                ? (array) $this->mailHelper->getTokens()
                : [];

            if ('' === $finalHtml) {
                $this->logger->warning('EmailPreRender: empty HTML after dispatchSendEvent', [
                    'contactId' => $lead->getId(),
                    'emailId'   => $email->getId(),
                ]);

                return false;
            }

            $contentHash = $this->buildContentHash($email);
            $contactHash = $this->buildContactHash($lead);

            $existing = $this->getRepository()->findValidCache(
                (int) $email->getId(),
                (int) $lead->getId(),
                $contentHash,
                $contactHash
            );
            if ($existing) {
                $this->em->remove($existing);
                $this->em->flush();
            }

            $cache = new EmailPrerenderCache();
            $cache->setEmailId((int) $email->getId())
                ->setContactId((int) $lead->getId())
                ->setContentHash($contentHash)
                ->setContactHash($contactHash)
                ->setSubject($finalSubject)
                ->setHtml($finalHtml)
                ->setPlainText($finalPlain ? (string) $finalPlain : null)
                ->setTokens($tokens)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setExpiresAt($expiresAt);

            $this->em->persist($cache);
            $this->em->flush();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('EmailPreRender: prerender failed: '.$e->getMessage(), [
                'exception' => $e,
                'contactId' => $lead->getId(),
                'emailId'   => $email->getId(),
            ]);

            return false;
        } finally {
            $this->mailHelper->reset(true);
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
