<?php

declare(strict_types=1);

return [
    'name'        => 'Email Pre-Render',
    'description' => 'Pre-compiles and caches fully rendered email payloads to speed up high-volume email.send events. Compatible with Advanced Templates and other render plugins.',
    'version'     => '1.0.0',
    'author'      => 'Wiesław Golec',

    'services' => [
        'events' => [
            'mautic.emailprerender.subscriber' => [
                'class'     => \MauticPlugin\MauticEmailPreRenderBundle\EventListener\EmailPrerenderSubscriber::class,
                'arguments' => [
                    'mautic.emailprerender.model',
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
                    'event_dispatcher',
                    'mautic.lead.model.lead',
                    'mautic.email.model.email',
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
        'repositories' => [
            'mautic.emailprerender.repository' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \MauticPlugin\MauticEmailPreRenderBundle\Entity\EmailPrerenderCache::class,
                ],
            ],
        ],
    ],
];
