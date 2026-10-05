<?php

declare(strict_types=1);

return [
    'name'        => 'Email Pre-Render',
    'description' => 'Pre-compiles and caches fully rendered email payloads (generate once, reuse on send). Campaign action + auto-invalidation on email/DWC change.',
    'version'     => '1.2.0',
    'author'      => 'Wiesław Golec',

    'parameters' => [
        'emailprerender.enabled'                 => true,
        'emailprerender.short_circuit'           => true,
        'emailprerender.auto_invalidate'         => true,
        'emailprerender.campaign_action_enabled' => true,
        'emailprerender.default_ttl_hours'       => 72,
    ],

    'services' => [
        'events' => [
            'mautic.emailprerender.subscriber' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\EventListener\EmailPrerenderSubscriber::class,
                'arguments' => [
                    'mautic.emailprerender.model',
                    'mautic.helper.core_parameters',
                    'monolog.logger.mautic',
                ],
                'tag' => 'kernel.event_subscriber',
            ],
            'mautic.emailprerender.campaign_subscriber' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    'mautic.emailprerender.model',
                    'mautic.email.model.email',
                    'mautic.helper.core_parameters',
                    'monolog.logger.mautic',
                ],
                'tag' => 'kernel.event_subscriber',
            ],
            'mautic.emailprerender.invalidation_subscriber' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\EventListener\CacheInvalidationSubscriber::class,
                'arguments' => [
                    'mautic.emailprerender.model',
                    'mautic.helper.core_parameters',
                    'doctrine.orm.entity_manager',
                    'monolog.logger.mautic',
                ],
                'tag' => 'kernel.event_subscriber',
            ],
        ],
        'models' => [
            'mautic.emailprerender.model' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.helper.mail',
                    'monolog.logger.mautic',
                ],
            ],
        ],
        'commands' => [
            'mautic.emailprerender.command.prerender' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\Command\PrerenderEmailCommand::class,
                'arguments' => [
                    'mautic.emailprerender.model',
                    'mautic.email.model.email',
                    'mautic.lead.model.list',
                    'mautic.lead.model.lead',
                    'doctrine.orm.entity_manager',
                ],
                'tag' => 'console.command',
            ],
            'mautic.emailprerender.command.clear' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\Command\ClearPrerenderCacheCommand::class,
                'arguments' => [
                    'mautic.emailprerender.model',
                ],
                'tag' => 'console.command',
            ],
        ],
    ],
];
