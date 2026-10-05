<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Tests\Unit\Entity;

use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCache;
use PHPUnit\Framework\TestCase;

final class EmailPrerenderCacheTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new EmailPrerenderCache();
        $now    = new \DateTimeImmutable('2026-01-01 12:00:00');
        $exp    = new \DateTimeImmutable('2026-01-04 12:00:00');

        $entity->setEmailId(10)
            ->setContactId(20)
            ->setContentHash('abc')
            ->setContactHash('def')
            ->setSubject('Hello')
            ->setHtml('<p>Hi</p>')
            ->setPlainText('Hi')
            ->setTokens(['{name}' => 'Ann'])
            ->setCreatedAt($now)
            ->setExpiresAt($exp);

        self::assertSame(10, $entity->getEmailId());
        self::assertSame(20, $entity->getContactId());
        self::assertSame('abc', $entity->getContentHash());
        self::assertSame('def', $entity->getContactHash());
        self::assertSame('Hello', $entity->getSubject());
        self::assertSame('<p>Hi</p>', $entity->getHtml());
        self::assertSame('Hi', $entity->getPlainText());
        self::assertSame(['{name}' => 'Ann'], $entity->getTokens());
        self::assertSame($now, $entity->getCreatedAt());
        self::assertSame($exp, $entity->getExpiresAt());
        self::assertNull($entity->getId());
    }

    public function testTokensDefaultEmpty(): void
    {
        $entity = new EmailPrerenderCache();
        self::assertSame([], $entity->getTokens());
    }
}
