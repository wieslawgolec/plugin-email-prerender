<?php

declare(strict_types=1);

/**
 * Minimal stubs so unit tests run without a full Mautic installation.
 */

namespace Mautic\CoreBundle\Helper {
    if (!class_exists(CoreParametersHelper::class, false)) {
        class CoreParametersHelper
        {
            /** @param array<string, mixed> $params */
            public function __construct(private array $params = [])
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->params[$key] ?? $default;
            }
        }
    }
}

namespace Mautic\CoreBundle\Entity {
    if (!class_exists(CommonRepository::class, false)) {
        class CommonRepository
        {
        }
    }
}

namespace Mautic\CoreBundle\Doctrine\Mapping {
    if (!class_exists(ClassMetadataBuilder::class, false)) {
        class ClassMetadataBuilder
        {
            public function __construct(mixed $metadata)
            {
            }

            public function setTable(string $name): self
            {
                return $this;
            }

            public function setCustomRepositoryClass(string $class): self
            {
                return $this;
            }

            public function addId(): self
            {
                return $this;
            }

            public function createField(string $name, string $type): self
            {
                return $this;
            }

            public function columnName(string $name): self
            {
                return $this;
            }

            public function length(int $len): self
            {
                return $this;
            }

            public function nullable(): self
            {
                return $this;
            }

            public function build(): self
            {
                return $this;
            }

            public function addIndex(array $cols, string $name): self
            {
                return $this;
            }
        }
    }
}

namespace Mautic\EmailBundle {
    if (!class_exists(EmailEvents::class, false)) {
        final class EmailEvents
        {
            public const EMAIL_ON_SEND   = 'mautic.email_on_send';
            public const EMAIL_POST_SAVE = 'mautic.email_post_save';
        }
    }
}

namespace Mautic\EmailBundle\Entity {
    if (!class_exists(Email::class, false)) {
        class Email
        {
            private ?int $id = null;
            private string $name = '';
            private string $subject = '';
            private string $customHtml = '';
            private ?string $plainText = null;
            private int $revision = 1;

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(int $id): self
            {
                $this->id = $id;

                return $this;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function setName(string $name): self
            {
                $this->name = $name;

                return $this;
            }

            public function getSubject(): string
            {
                return $this->subject;
            }

            public function setSubject(string $subject): self
            {
                $this->subject = $subject;

                return $this;
            }

            public function getCustomHtml(): string
            {
                return $this->customHtml;
            }

            public function setCustomHtml(string $html): self
            {
                $this->customHtml = $html;

                return $this;
            }

            public function getPlainText(): ?string
            {
                return $this->plainText;
            }

            public function setPlainText(?string $plainText): self
            {
                $this->plainText = $plainText;

                return $this;
            }

            public function getRevision(): int
            {
                return $this->revision;
            }

            public function setRevision(int $revision): self
            {
                $this->revision = $revision;

                return $this;
            }
        }
    }
}

namespace Mautic\EmailBundle\Event {
    if (!class_exists(EmailSendEvent::class, false)) {
        class EmailSendEvent extends \Symfony\Contracts\EventDispatcher\Event
        {
            private mixed $content = '';
            private mixed $subject = '';
            private mixed $plainText = null;
            /** @var array<string, mixed> */
            private array $tokens = [];
            private bool $internalSend = false;
            private mixed $email = null;
            private mixed $lead = null;

            public function isInternalSend(): bool
            {
                return $this->internalSend;
            }

            public function setInternalSend(bool $internal): self
            {
                $this->internalSend = $internal;

                return $this;
            }

            public function getEmail(): mixed
            {
                return $this->email;
            }

            public function setEmail(mixed $email): self
            {
                $this->email = $email;

                return $this;
            }

            public function getLead(): mixed
            {
                return $this->lead;
            }

            public function setLead(mixed $lead): self
            {
                $this->lead = $lead;

                return $this;
            }

            public function getContent(bool $replaceTokens = false): mixed
            {
                return $this->content;
            }

            public function setContent(mixed $content): void
            {
                $this->content = $content;
            }

            public function getSubject(): mixed
            {
                return $this->subject;
            }

            public function setSubject(mixed $subject): void
            {
                $this->subject = $subject;
            }

            public function getPlainText(): mixed
            {
                return $this->plainText;
            }

            public function setPlainText(mixed $plainText): void
            {
                $this->plainText = $plainText;
            }

            /** @param array<string, mixed> $tokens */
            public function addTokens(array $tokens): void
            {
                $this->tokens = array_merge($this->tokens, $tokens);
            }

            /** @return array<string, mixed> */
            public function getTokens(): array
            {
                return $this->tokens;
            }
        }
    }

    if (!class_exists(EmailEvent::class, false)) {
        class EmailEvent
        {
            /** @param array<string, mixed> $changes */
            public function __construct(
                private mixed $email = null,
                private array $changes = [],
            ) {
            }

            public function getEmail(): mixed
            {
                return $this->email;
            }

            /** @return array<string, mixed> */
            public function getChanges(): array
            {
                return $this->changes;
            }
        }
    }
}

namespace Mautic\EmailBundle\Helper {
    if (!class_exists(MailHelper::class, false)) {
        class MailHelper
        {
            private mixed $body = '';
            private mixed $subject = '';
            private mixed $plainText = null;
            /** @var array<string, mixed> */
            private array $tokens = [];

            public function reset(bool $cleanSlate = true): void
            {
                $this->body = '';
                $this->subject = '';
                $this->plainText = null;
                $this->tokens = [];
            }

            public function setEmail(mixed $email): void
            {
            }

            public function setLead(mixed $lead): void
            {
            }

            public function setIdHash(?string $hash = null): void
            {
            }

            /** @param array<int|string, mixed> $source */
            public function setSource(array $source): void
            {
            }

            public function setBody(mixed $content, string $type = 'text/html', mixed $charset = null, bool $ignore = false): void
            {
                $this->body = $content;
            }

            public function setSubject(mixed $subject): void
            {
                $this->subject = $subject;
            }

            public function setPlainText(mixed $text): void
            {
                $this->plainText = $text;
            }

            public function dispatchSendEvent(): void
            {
                $this->body = (string) $this->body.'<!--rendered-->';
                $this->tokens['{test}'] = 'value';
            }

            public function getBody(): mixed
            {
                return $this->body;
            }

            public function getSubject(): mixed
            {
                return $this->subject;
            }

            public function getPlainText(): mixed
            {
                return $this->plainText;
            }

            /** @return array<string, mixed> */
            public function getTokens(): array
            {
                return $this->tokens;
            }
        }
    }
}

namespace Mautic\EmailBundle\Model {
    if (!class_exists(EmailModel::class, false)) {
        class EmailModel
        {
            public function getEntity(mixed $id): mixed
            {
                return null;
            }
        }
    }
}

namespace Mautic\EmailBundle\Form\Type {
    if (!class_exists(EmailSendType::class, false)) {
        class EmailSendType
        {
        }
    }
}

namespace Mautic\LeadBundle\Entity {
    if (!class_exists(Lead::class, false)) {
        class Lead
        {
            private ?int $id = null;
            private ?string $email = null;
            /** @var array<string, mixed> */
            private array $profileFields = [];

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(int $id): self
            {
                $this->id = $id;

                return $this;
            }

            public function getEmail(): ?string
            {
                return $this->email;
            }

            public function setEmail(?string $email): self
            {
                $this->email = $email;

                return $this;
            }

            /** @return array<string, mixed> */
            public function getProfileFields(): array
            {
                return $this->profileFields + [
                    'id' => $this->id,
                    'email' => $this->email,
                ];
            }

            /** @param array<string, mixed> $fields */
            public function setProfileFields(array $fields): self
            {
                $this->profileFields = $fields;

                return $this;
            }
        }
    }
}

namespace Mautic\CampaignBundle {
    if (!class_exists(CampaignEvents::class, false)) {
        final class CampaignEvents
        {
            public const CAMPAIGN_ON_BUILD = 'mautic.campaign_on_build';
        }
    }
}

namespace Mautic\CampaignBundle\Event {
    if (!class_exists(CampaignBuilderEvent::class, false)) {
        class CampaignBuilderEvent
        {
            /** @var array<string, array<string, mixed>> */
            private array $actions = [];

            /** @param array<string, mixed> $config */
            public function addAction(string $key, array $config): void
            {
                $this->actions[$key] = $config;
            }

            /** @return array<string, array<string, mixed>> */
            public function getActions(): array
            {
                return $this->actions;
            }
        }
    }

    if (!class_exists(PendingEvent::class, false)) {
        class PendingEvent
        {
            private string $context = '';
            /** @var array<string, mixed> */
            private array $config = [];
            /** @var array<int, object> */
            private array $contacts = [];
            /** @var array<int, object> */
            private array $logs = [];
            /** @var list<object> */
            public array $passed = [];
            /** @var list<array{0:object,1:string}> */
            public array $passedWithError = [];

            public function setContext(string $context): self
            {
                $this->context = $context;

                return $this;
            }

            public function checkContext(string $context): bool
            {
                return $this->context === $context;
            }

            /** @param array<string, mixed> $config */
            public function setConfig(array $config): self
            {
                $this->config = $config;

                return $this;
            }

            /** @return array<string, mixed> */
            public function getConfig(): array
            {
                return $this->config;
            }

            /** @param array<int, object> $contacts */
            public function setContacts(array $contacts): self
            {
                $this->contacts = $contacts;

                return $this;
            }

            /** @return array<int, object> */
            public function getContactsKeyedById(): array
            {
                return $this->contacts;
            }

            /** @return list<object> */
            public function getPending(): array
            {
                return array_values($this->logs);
            }

            public function setLog(int $contactId, object $log): self
            {
                $this->logs[$contactId] = $log;

                return $this;
            }

            public function findLogByContactId(int $contactId): object
            {
                return $this->logs[$contactId] ?? new \stdClass();
            }

            public function pass(object $log): void
            {
                $this->passed[] = $log;
            }

            public function passWithError(object $log, string $message): void
            {
                $this->passedWithError[] = [$log, $message];
            }
        }
    }
}

namespace Mautic\PluginBundle\Bundle {
    if (!class_exists(PluginBundleBase::class, false)) {
        class PluginBundleBase
        {
        }
    }
}

namespace Symfony\Contracts\EventDispatcher {
    if (!class_exists(Event::class, false)) {
        class Event
        {
            private bool $propagationStopped = false;

            public function stopPropagation(): void
            {
                $this->propagationStopped = true;
            }

            public function isPropagationStopped(): bool
            {
                return $this->propagationStopped;
            }
        }
    }
}

namespace Symfony\Component\EventDispatcher {
    if (!interface_exists(EventSubscriberInterface::class, false)) {
        interface EventSubscriberInterface
        {
            /** @return array<string, mixed> */
            public static function getSubscribedEvents(): array;
        }
    }
}

namespace Doctrine\ORM {
    if (!interface_exists(EntityManagerInterface::class, false)) {
        interface EntityManagerInterface
        {
            public function getRepository(string $className): object;

            public function persist(object $entity): void;

            public function remove(object $entity): void;

            public function flush(): void;

            public function getConnection(): object;
        }
    }
}

namespace Doctrine\ORM\Mapping {
    if (!class_exists(ClassMetadata::class, false)) {
        class ClassMetadata
        {
        }
    }
}
