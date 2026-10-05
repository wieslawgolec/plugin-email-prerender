<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCache;
use MauticPlugin\MauticEmailPreRenderBundle\EventListener\EmailPrerenderSubscriber;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class EmailPrerenderSubscriberTest extends TestCase
{
    private PrerenderModel&MockObject $model;

    protected function setUp(): void
    {
        $this->model = $this->createMock(PrerenderModel::class);
    }

    private function subscriber(array $params = []): EmailPrerenderSubscriber
    {
        $defaults = [
            'emailprerender.enabled'       => true,
            'emailprerender.short_circuit' => true,
        ];

        return new EmailPrerenderSubscriber(
            $this->model,
            new CoreParametersHelper(array_merge($defaults, $params)),
            new NullLogger()
        );
    }

    public function testSubscribedEventsPriority(): void
    {
        $events = EmailPrerenderSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(EmailEvents::EMAIL_ON_SEND, $events);
        self::assertSame(['onEmailSendUseCache', 255], $events[EmailEvents::EMAIL_ON_SEND]);
    }

    public function testSkipsWhenDisabled(): void
    {
        $this->model->expects(self::never())->method('findCache');
        $event = new EmailSendEvent();
        $event->setEmail((new Email())->setId(1));
        $event->setLead((new Lead())->setId(1));

        $this->subscriber(['emailprerender.enabled' => false])->onEmailSendUseCache($event);
        self::assertFalse($event->isPropagationStopped());
    }

    public function testSkipsInternalSend(): void
    {
        $this->model->expects(self::never())->method('findCache');
        $event = new EmailSendEvent();
        $event->setInternalSend(true);
        $event->setEmail((new Email())->setId(1));
        $event->setLead((new Lead())->setId(1));

        $this->subscriber()->onEmailSendUseCache($event);
    }

    public function testCacheMissDoesNothing(): void
    {
        $email = (new Email())->setId(1);
        $lead  = (new Lead())->setId(2);
        $this->model->method('findCache')->willReturn(null);

        $event = new EmailSendEvent();
        $event->setEmail($email);
        $event->setLead($lead);
        $event->setContent('original');

        $this->subscriber()->onEmailSendUseCache($event);
        self::assertSame('original', $event->getContent());
        self::assertFalse($event->isPropagationStopped());
    }

    public function testCacheHitInjectsAndShortCircuits(): void
    {
        $email = (new Email())->setId(1);
        $lead  = (new Lead())->setId(2);
        $cache = (new EmailPrerenderCache())
            ->setHtml('<p>cached</p>')
            ->setSubject('Cached Subject')
            ->setPlainText('cached')
            ->setTokens(['{x}' => 'y']);

        $this->model->method('findCache')->willReturn($cache);

        $event = new EmailSendEvent();
        $event->setEmail($email);
        $event->setLead($lead);

        $this->subscriber()->onEmailSendUseCache($event);

        self::assertSame('<p>cached</p>', $event->getContent());
        self::assertSame('Cached Subject', $event->getSubject());
        self::assertSame('cached', $event->getPlainText());
        self::assertSame(['{x}' => 'y'], $event->getTokens());
        self::assertTrue($event->isPropagationStopped());
    }

    public function testCacheHitWithoutShortCircuitDoesNotStopPropagation(): void
    {
        $email = (new Email())->setId(1);
        $lead  = (new Lead())->setId(2);
        $cache = (new EmailPrerenderCache())
            ->setHtml('<p>cached</p>')
            ->setSubject('Sub');

        $this->model->method('findCache')->willReturn($cache);

        $event = new EmailSendEvent();
        $event->setEmail($email);
        $event->setLead($lead);

        $this->subscriber(['emailprerender.short_circuit' => false])->onEmailSendUseCache($event);

        self::assertSame('<p>cached</p>', $event->getContent());
        self::assertFalse($event->isPropagationStopped());
    }

    public function testAcceptsArrayLead(): void
    {
        $email = (new Email())->setId(1);
        $cache = (new EmailPrerenderCache())->setHtml('H')->setSubject('S');
        $this->model->method('findCache')->willReturn($cache);

        $event = new EmailSendEvent();
        $event->setEmail($email);
        $event->setLead(['id' => 99, 'email' => 'a@b.c']);

        $this->subscriber()->onEmailSendUseCache($event);
        self::assertSame('H', $event->getContent());
    }
}
