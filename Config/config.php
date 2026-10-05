<?php

declare(strict_types=1);

return [
    'name'        => 'Email Pre-Render',
    'description' => 'Pre-compiles and caches fully rendered email payloads (generate once, reuse on send). Configurable short-circuit for third-party plugin compatibility.',
    'version'     => '1.1.0',
    'author'      => 'Wiesław Golec',

    'parameters' => [
        // Master switch for send-time cache reuse
        'emailprerender.enabled' => true,
        // On cache hit, stop further EMAIL_ON_SEND listeners (true = max speed; false = safer with some 3rd-party plugins)
        'emailprerender.short_circuit' => true,
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
