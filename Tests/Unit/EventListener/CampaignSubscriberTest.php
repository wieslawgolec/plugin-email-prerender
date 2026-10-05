<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Form\Type\EmailSendType;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailPreRenderBundle\EmailPreRenderEvents;
use MauticPlugin\MauticEmailPreRenderBundle\EventListener\CampaignSubscriber;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CampaignSubscriberTest extends TestCase
{
    private PrerenderModel&MockObject $prerenderModel;
    private EmailModel&MockObject $emailModel;

    protected function setUp(): void
    {
        $this->prerenderModel = $this->createMock(PrerenderModel::class);
        $this->emailModel     = $this->createMock(EmailModel::class);
    }

    private function subscriber(array $params = []): CampaignSubscriber
    {
        $defaults = [
            'emailprerender.campaign_action_enabled' => true,
            'emailprerender.default_ttl_hours'       => 72,
        ];

        return new CampaignSubscriber(
            $this->prerenderModel,
            $this->emailModel,
            new CoreParametersHelper(array_merge($defaults, $params)),
            new NullLogger()
        );
    }

    public function testSubscribedEvents(): void
    {
        $events = CampaignSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(CampaignEvents::CAMPAIGN_ON_BUILD, $events);
        self::assertArrayHasKey(EmailPreRenderEvents::ON_CAMPAIGN_BATCH_ACTION, $events);
    }

    public function testRegistersActionWithEmailSendType(): void
    {
        $builder = new CampaignBuilderEvent();
        $this->subscriber()->onCampaignBuild($builder);

        $actions = $builder->getActions();
        self::assertArrayHasKey(CampaignSubscriber::ACTION_TYPE, $actions);
        self::assertSame(EmailSendType::class, $actions[CampaignSubscriber::ACTION_TYPE]['formType']);
        self::assertSame(
            EmailPreRenderEvents::ON_CAMPAIGN_BATCH_ACTION,
            $actions[CampaignSubscriber::ACTION_TYPE]['batchEventName']
        );
    }

    public function testDoesNotRegisterWhenDisabled(): void
    {
        $builder = new CampaignBuilderEvent();
        $this->subscriber(['emailprerender.campaign_action_enabled' => false])->onCampaignBuild($builder);
        self::assertSame([], $builder->getActions());
    }

    public function testBatchIgnoresWrongContext(): void
    {
        $pending = (new PendingEvent())->setContext('other.action');
        $this->prerenderModel->expects(self::never())->method('prerenderForContact');
        $this->subscriber()->onCampaignBatchAction($pending);
    }

    public function testBatchFailsWithoutEmailConfig(): void
    {
        $log = new \stdClass();
        $pending = (new PendingEvent())
            ->setContext(CampaignSubscriber::ACTION_TYPE)
            ->setConfig([])
            ->setLog(1, $log);

        $this->subscriber()->onCampaignBatchAction($pending);
        self::assertCount(1, $pending->passedWithError);
    }

    public function testBatchPassesOnSuccessfulPrerender(): void
    {
        $email = (new Email())->setId(5)->setSubject('S')->setCustomHtml('H');
        $lead  = (new Lead())->setId(11)->setEmail('ok@example.com');
        $log   = new \stdClass();

        $this->emailModel->method('getEntity')->with(5)->willReturn($email);
        $this->prerenderModel->expects(self::once())
            ->method('prerenderForContact')
            ->with($email, $lead, self::isInstanceOf(\DateTimeInterface::class))
            ->willReturn(true);

        $pending = (new PendingEvent())
            ->setContext(CampaignSubscriber::ACTION_TYPE)
            ->setConfig(['email' => 5])
            ->setContacts([11 => $lead])
            ->setLog(11, $log);

        $this->subscriber()->onCampaignBatchAction($pending);
        self::assertCount(1, $pending->passed);
        self::assertCount(0, $pending->passedWithError);
    }

    public function testBatchPassWithErrorWhenContactHasNoEmail(): void
    {
        $email = (new Email())->setId(5)->setSubject('S')->setCustomHtml('H');
        $lead  = (new Lead())->setId(11)->setEmail(null);
        $log   = new \stdClass();

        $this->emailModel->method('getEntity')->willReturn($email);
        $this->prerenderModel->expects(self::never())->method('prerenderForContact');

        $pending = (new PendingEvent())
            ->setContext(CampaignSubscriber::ACTION_TYPE)
            ->setConfig(['email' => 5])
            ->setContacts([11 => $lead])
            ->setLog(11, $log);

        $this->subscriber()->onCampaignBatchAction($pending);
        self::assertCount(1, $pending->passedWithError);
    }
}
