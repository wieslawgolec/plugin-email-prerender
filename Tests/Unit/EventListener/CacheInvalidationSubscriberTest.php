<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailEvent;
use MauticPlugin\MauticEmailPreRenderBundle\EventListener\CacheInvalidationSubscriber;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CacheInvalidationSubscriberTest extends TestCase
{
    private PrerenderModel&MockObject $model;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->model = $this->createMock(PrerenderModel::class);
        $this->em    = $this->createMock(EntityManagerInterface::class);
    }

    private function subscriber(array $params = []): CacheInvalidationSubscriber
    {
        $defaults = ['emailprerender.auto_invalidate' => true];

        return new CacheInvalidationSubscriber(
            $this->model,
            new CoreParametersHelper(array_merge($defaults, $params)),
            $this->em,
            new NullLogger()
        );
    }

    public function testListensToEmailPostSave(): void
    {
        $events = CacheInvalidationSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(EmailEvents::EMAIL_POST_SAVE, $events);
    }

    public function testSkipsWhenAutoInvalidateDisabled(): void
    {
        $this->model->expects(self::never())->method('clearByEmailId');
        $email = (new Email())->setId(3);
        $event = new EmailEvent($email, ['customHtml' => ['old', 'new']]);

        $this->subscriber(['emailprerender.auto_invalidate' => false])->onEmailPostSave($event);
    }

    public function testClearsOnCustomHtmlChange(): void
    {
        $this->model->expects(self::once())->method('clearByEmailId')->with(3)->willReturn(5);
        $email = (new Email())->setId(3);
        $event = new EmailEvent($email, ['customHtml' => ['old', 'new']]);

        $this->subscriber()->onEmailPostSave($event);
    }

    public function testClearsOnDynamicContentChange(): void
    {
        $this->model->expects(self::once())->method('clearByEmailId')->with(8)->willReturn(1);
        $email = (new Email())->setId(8);
        $event = new EmailEvent($email, ['dynamicContent' => [[], ['token' => 'x']]]);

        $this->subscriber()->onEmailPostSave($event);
    }

    public function testDoesNotClearOnUnrelatedChange(): void
    {
        $this->model->expects(self::never())->method('clearByEmailId');
        $email = (new Email())->setId(3);
        $event = new EmailEvent($email, ['name' => ['Old', 'New']]);

        $this->subscriber()->onEmailPostSave($event);
    }
}
