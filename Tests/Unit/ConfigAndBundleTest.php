<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Tests\Unit;

use MauticPlugin\MauticEmailPreRenderBundle\EmailPreRenderEvents;
use MauticPlugin\MauticEmailPreRenderBundle\MauticEmailPreRenderBundle;
use PHPUnit\Framework\TestCase;

final class ConfigAndBundleTest extends TestCase
{
    public function testConfigFileStructure(): void
    {
        $config = require dirname(__DIR__, 2).'/Config/config.php';

        self::assertSame('Email Pre-Render', $config['name']);
        self::assertArrayHasKey('parameters', $config);
        self::assertTrue($config['parameters']['emailprerender.enabled']);
        self::assertTrue($config['parameters']['emailprerender.short_circuit']);
        self::assertTrue($config['parameters']['emailprerender.auto_invalidate']);
        self::assertTrue($config['parameters']['emailprerender.campaign_action_enabled']);
        self::assertSame(72, $config['parameters']['emailprerender.default_ttl_hours']);

        self::assertArrayHasKey('mautic.emailprerender.subscriber', $config['services']['events']);
        self::assertArrayHasKey('mautic.emailprerender.campaign_subscriber', $config['services']['events']);
        self::assertArrayHasKey('mautic.emailprerender.invalidation_subscriber', $config['services']['events']);
        self::assertArrayHasKey('mautic.emailprerender.model', $config['services']['models']);
    }

    public function testBundleClassExists(): void
    {
        self::assertTrue(class_exists(MauticEmailPreRenderBundle::class));
    }

    public function testEventConstant(): void
    {
        self::assertSame(
            'mautic.emailprerender.on_campaign_batch_action',
            EmailPreRenderEvents::ON_CAMPAIGN_BATCH_ACTION
        );
    }

    public function testTranslationFileExists(): void
    {
        $path = dirname(__DIR__, 2).'/Translations/en_US/messages.ini';
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertStringContainsString('emailprerender.campaign.event.prerender', $content);
    }
}
