<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Tests\Unit\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCache;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCacheRepository;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PrerenderModelTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MailHelper $mailHelper;
    private EmailPrerenderCacheRepository&MockObject $repo;
    private PrerenderModel $model;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->mailHelper = new MailHelper();
        $this->repo       = $this->createMock(EmailPrerenderCacheRepository::class);

        $this->em->method('getRepository')->willReturn($this->repo);

        $this->model = new PrerenderModel($this->em, $this->mailHelper, new NullLogger());
    }

    public function testBuildContentHashIsStableAndChangesWithHtml(): void
    {
        $email = (new Email())->setId(1)->setSubject('S')->setCustomHtml('<p>A</p>')->setRevision(1);
        $h1    = $this->model->buildContentHash($email);
        $h2    = $this->model->buildContentHash($email);
        self::assertSame($h1, $h2);
        self::assertSame(64, strlen($h1));

        $email->setCustomHtml('<p>B</p>');
        self::assertNotSame($h1, $this->model->buildContentHash($email));
    }

    public function testBuildContactHashDiffersPerProfile(): void
    {
        $lead1 = (new Lead())->setId(1)->setEmail('a@example.com')->setProfileFields(['firstname' => 'A']);
        $lead2 = (new Lead())->setId(2)->setEmail('b@example.com')->setProfileFields(['firstname' => 'B']);

        self::assertNotSame(
            $this->model->buildContactHash($lead1),
            $this->model->buildContactHash($lead2)
        );
    }

    public function testBuildContactHashAcceptsArrayLead(): void
    {
        $hash = $this->model->buildContactHash(['id' => 5, 'email' => 'x@y.z', 'firstname' => 'X']);
        self::assertSame(64, strlen($hash));
    }

    public function testFindCacheReturnsNullForMissingContactId(): void
    {
        $email = (new Email())->setId(1)->setSubject('S')->setCustomHtml('H');
        self::assertNull($this->model->findCache($email, ['email' => 'no-id@test.com']));
    }

    public function testFindCacheDelegatesToRepository(): void
    {
        $email = (new Email())->setId(3)->setSubject('S')->setCustomHtml('H');
        $lead  = (new Lead())->setId(9)->setEmail('c@example.com');
        $cache = (new EmailPrerenderCache())->setEmailId(3)->setContactId(9);

        $this->repo->expects(self::once())
            ->method('findValidCache')
            ->with(
                3,
                9,
                self::isType('string'),
                self::isType('string')
            )
            ->willReturn($cache);

        self::assertSame($cache, $this->model->findCache($email, $lead));
    }

    public function testPrerenderForContactFailsWithoutEmailAddress(): void
    {
        $email = (new Email())->setId(1)->setSubject('S')->setCustomHtml('<p>x</p>');
        $lead  = (new Lead())->setId(1)->setEmail(null);

        self::assertFalse($this->model->prerenderForContact($email, $lead));
    }

    public function testPrerenderForContactPersistsRenderedPayload(): void
    {
        $email = (new Email())
            ->setId(7)
            ->setSubject('Welcome')
            ->setCustomHtml('<p>Hello</p>')
            ->setPlainText('Hello');
        $lead = (new Lead())->setId(15)->setEmail('user@example.com')->setProfileFields(['firstname' => 'User']);

        $this->repo->method('findValidCache')->willReturn(null);

        $persisted = null;
        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($entity) use (&$persisted) {
                $persisted = $entity;

                return $entity instanceof EmailPrerenderCache;
            }));
        $this->em->expects(self::atLeastOnce())->method('flush');

        self::assertTrue($this->model->prerenderForContact($email, $lead));
        self::assertInstanceOf(EmailPrerenderCache::class, $persisted);
        self::assertSame(7, $persisted->getEmailId());
        self::assertSame(15, $persisted->getContactId());
        self::assertStringContainsString('<!--rendered-->', $persisted->getHtml());
        self::assertSame('Welcome', $persisted->getSubject());
        self::assertArrayHasKey('{test}', $persisted->getTokens());
    }

    public function testClearHelpersDelegate(): void
    {
        $this->repo->expects(self::once())->method('deleteByEmailId')->with(5)->willReturn(3);
        $this->repo->expects(self::once())->method('deleteAll')->willReturn(10);
        $this->repo->expects(self::once())->method('deleteExpired')->willReturn(2);

        self::assertSame(3, $this->model->clearByEmailId(5));
        self::assertSame(10, $this->model->clearAll());
        self::assertSame(2, $this->model->clearExpired());
    }
}
