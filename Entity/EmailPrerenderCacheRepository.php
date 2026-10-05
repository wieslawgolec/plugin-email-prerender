<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<EmailPrerenderCache>
 */
class EmailPrerenderCacheRepository extends CommonRepository
{
    public function findValidCache(
        int $emailId,
        int $contactId,
        string $contentHash,
        string $contactHash
    ): ?EmailPrerenderCache {
        $qb = $this->createQueryBuilder('c');

        $qb->where('c.emailId = :emailId')
            ->andWhere('c.contactId = :contactId')
            ->andWhere('c.contentHash = :contentHash')
            ->andWhere('c.contactHash = :contactHash')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('c.expiresAt'),
                    $qb->expr()->gt('c.expiresAt', ':now')
                )
            )
            ->setParameter('emailId', $emailId)
            ->setParameter('contactId', $contactId)
            ->setParameter('contentHash', $contentHash)
            ->setParameter('contactHash', $contactHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function deleteByEmailId(int $emailId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->where('c.emailId = :emailId')
            ->setParameter('emailId', $emailId)
            ->getQuery()
            ->execute();
    }

    public function deleteAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->where('c.expiresAt IS NOT NULL')
            ->andWhere('c.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
