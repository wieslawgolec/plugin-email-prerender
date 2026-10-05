<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * Fully rendered email payload for reuse on email.send (generate once).
 */
class EmailPrerenderCache
{
    private ?int $id = null;

    private int $emailId;

    private int $contactId;

    private string $contentHash;

    private string $contactHash;

    private string $subject;

    private string $html;

    private ?string $plainText = null;

    /** @var array<string, mixed> */
    private array $tokens = [];

    private \DateTimeInterface $createdAt;

    private ?\DateTimeInterface $expiresAt = null;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('email_prerender_cache')
            ->setCustomRepositoryClass(EmailPrerenderCacheRepository::class);

        $builder->addId();

        $builder->createField('emailId', 'integer')
            ->columnName('email_id')
            ->build();

        $builder->createField('contactId', 'integer')
            ->columnName('contact_id')
            ->build();

        $builder->createField('contentHash', 'string')
            ->columnName('content_hash')
            ->length(64)
            ->build();

        $builder->createField('contactHash', 'string')
            ->columnName('contact_hash')
            ->length(64)
            ->build();

        $builder->createField('subject', 'text')
            ->build();

        $builder->createField('html', 'text')
            ->build();

        $builder->createField('plainText', 'text')
            ->columnName('plain_text')
            ->nullable()
            ->build();

        $builder->createField('tokens', 'json')
            ->nullable()
            ->build();

        $builder->createField('createdAt', 'datetime')
            ->columnName('created_at')
            ->build();

        $builder->createField('expiresAt', 'datetime')
            ->columnName('expires_at')
            ->nullable()
            ->build();

        $builder->addIndex(['email_id', 'contact_id', 'content_hash', 'contact_hash'], 'uniq_email_contact_hash');
        $builder->addIndex(['expires_at'], 'idx_expires');
        $builder->addIndex(['email_id'], 'idx_email_id');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmailId(): int
    {
        return $this->emailId;
    }

    public function setEmailId(int $emailId): self
    {
        $this->emailId = $emailId;

        return $this;
    }

    public function getContactId(): int
    {
        return $this->contactId;
    }

    public function setContactId(int $contactId): self
    {
        $this->contactId = $contactId;

        return $this;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): self
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function getContactHash(): string
    {
        return $this->contactHash;
    }

    public function setContactHash(string $contactHash): self
    {
        $this->contactHash = $contactHash;

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

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): self
    {
        $this->html = $html;

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

    /**
     * @return array<string, mixed>
     */
    public function getTokens(): array
    {
        return $this->tokens ?? [];
    }

    /**
     * @param array<string, mixed> $tokens
     */
    public function setTokens(array $tokens): self
    {
        $this->tokens = $tokens;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
