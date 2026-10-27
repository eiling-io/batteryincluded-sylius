<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Dto;

use BatteryIncludedSdk\Dto\AbstractTranslatableDto;

/**
 * Content page, exported with the id `CONTENT-<identifier>` and the fields in `_CONTENT` and `_CONTENT_i18n`.
 * The shop finds the page of a hit by its `code`, like the product of a hit by its `ordernumber`.
 */
final class ContentPageDto extends AbstractTranslatableDto
{
    public const TYPE = 'CONTENT';

    private ?string $code = null;

    private ?string $title = null;

    private ?string $teaser = null;

    private ?string $content = null;

    private ?string $url = null;

    public function __construct(string $identifier)
    {
        parent::__construct($identifier, self::TYPE);
    }

    // the code is the same in every language
    protected function getTranslatableFields(): array
    {
        return ['title', 'teaser', 'content', 'url'];
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTeaser(): ?string
    {
        return $this->teaser;
    }

    public function setTeaser(?string $teaser): static
    {
        $this->teaser = $teaser;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function jsonSerialize(): array
    {
        $json = parent::jsonSerialize();
        $json['_' . $this->getType()] = $this->filterJsonValues([
            'code' => $this->code,
            'title' => $this->title,
            'teaser' => $this->teaser,
            'content' => $this->content,
            'url' => $this->url,
        ]);

        return $json;
    }
}
