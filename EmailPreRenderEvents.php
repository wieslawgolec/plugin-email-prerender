<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle;

/**
 * Plugin event names.
 */
final class EmailPreRenderEvents
{
    /**
     * Batch execution of the campaign action "Pre-render email".
     * Listener receives Mautic\CampaignBundle\Event\PendingEvent.
     */
    public const ON_CAMPAIGN_BATCH_ACTION = 'mautic.emailprerender.on_campaign_batch_action';
}
