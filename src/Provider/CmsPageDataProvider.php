<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Provider;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Batteryincluded\BatteryincludedBundle\Provider\CollectionAwareDataProviderInterface;
use Batteryincluded\BatteryincludedBundle\Provider\PartialDataProviderInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EilingIo\SyliusBatteryIncludedPlugin\Dto\ContentPageDto;
use Sylius\CmsPlugin\Entity\ContentConfigurationInterface;
use Sylius\CmsPlugin\Entity\PageInterface;
use Sylius\CmsPlugin\Entity\PageTranslationInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Delivers the enabled and published pages of the Sylius CMS plugin of one channel to its collection. Only registered
 * if the CMS plugin is installed and enabled, one instance per configured channel.
 */
final class CmsPageDataProvider implements CollectionAwareDataProviderInterface, PartialDataProviderInterface
{
    /**
     * Content elements whose text is searchable, type => key of the text in the configuration.
     */
    private const TEXT_ELEMENTS = [
        'heading' => 'heading',
        'textarea' => 'textarea',
    ];

    private ?ChannelInterface $channel = null;

    /**
     * @param EntityRepository<PageInterface> $pageRepository
     */
    public function __construct(
        private readonly EntityRepository $pageRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly UrlGeneratorInterface $router,
        private readonly string $collection = BatteryincludedBundle::DEFAULT_COLLECTION,
        private readonly ?string $channelCode = null,
    ) {
    }

    public function supportsCollection(string $collection): bool
    {
        return $collection === $this->collection;
    }

    public function getBatches(int $batchSize): \Generator
    {
        $channel = $this->getChannel();
        $offset = 0;

        do {
            /** @var list<PageInterface> $pages */
            $pages = $this->createQueryBuilder($channel)
                ->orderBy('o.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            $offset += $batchSize;

            if ($pages !== []) {
                yield array_map(fn (PageInterface $page) => $this->createDto($page, $channel), $pages);
            }
        } while (count($pages) === $batchSize);
    }

    public function getByIds(array $ids): iterable
    {
        $pageIds = [];
        foreach ($ids as $id) {
            if (str_starts_with($id, ContentPageDto::TYPE . '-')) {
                $pageIds[] = substr($id, strlen(ContentPageDto::TYPE) + 1);
            }
        }

        if ($pageIds === []) {
            return [];
        }

        $channel = $this->getChannel();
        /** @var list<PageInterface> $pages */
        $pages = $this->createQueryBuilder($channel)
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getResult();

        return array_map(fn (PageInterface $page) => $this->createDto($page, $channel), $pages);
    }

    private function createQueryBuilder(ChannelInterface $channel): QueryBuilder
    {
        return $this->pageRepository->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('o.enabled = :enabled')
            ->andWhere('o.publishAt IS NULL OR o.publishAt <= :now')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->setParameter('now', new \DateTimeImmutable());
    }

    private function getChannel(): ChannelInterface
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $channel = $this->channelCode !== null
            ? $this->channelRepository->findOneByCode($this->channelCode)
            : ($this->channelRepository->findEnabled()[0] ?? null);

        if (!$channel instanceof ChannelInterface) {
            throw new \RuntimeException(sprintf('The channel "%s" of the collection "%s" does not exist.', $this->channelCode ?? '(first enabled)', $this->collection));
        }

        return $this->channel = $channel;
    }

    private function createDto(PageInterface $page, ChannelInterface $channel): ContentPageDto
    {
        $dto = (new ContentPageDto((string) $page->getId()))->setCode($page->getCode());

        $translations = [];
        foreach ($page->getTranslations() as $translation) {
            if ($translation instanceof PageTranslationInterface) {
                $translations[(string) $translation->getLocale()] = $translation;
            }
        }

        // the default locale of the channel is the fallback for languages without translation
        $defaultLocale = $channel->getDefaultLocale()?->getCode();
        $default = $translations[$defaultLocale] ?? reset($translations);
        if ($default instanceof PageTranslationInterface) {
            $fields = $this->getFields($page, $default);
            $dto->setTitle($fields['title'])
                ->setTeaser($fields['teaser'])
                ->setContent($fields['content'])
                ->setUrl($fields['url']);
        }

        foreach ($translations as $locale => $translation) {
            foreach ($this->getFields($page, $translation) as $field => $value) {
                if ($value !== null) {
                    $dto->addTranslation($locale, $field, $value);
                }
            }
        }

        return $dto;
    }

    /**
     * @return array{title: ?string, teaser: ?string, content: ?string, url: ?string}
     */
    private function getFields(PageInterface $page, PageTranslationInterface $translation): array
    {
        $locale = (string) $translation->getLocale();
        $slug = $translation->getSlug();

        return [
            'title' => $translation->getTitle() ?? $page->getName(),
            'teaser' => $this->toText($translation->getTeaserContent()),
            'content' => $this->getContent($page, $locale),
            'url' => $slug !== null && $slug !== ''
                ? $this->router->generate('sylius_cms_shop_page_show', ['slug' => $slug, '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL)
                : null,
        ];
    }

    /**
     * Text of the headings and text areas of the locale, paragraphs separated by a blank line.
     */
    private function getContent(PageInterface $page, string $locale): ?string
    {
        $texts = [];
        /** @var ContentConfigurationInterface $element */
        foreach ($page->getContentElements() as $element) {
            $key = self::TEXT_ELEMENTS[(string) $element->getType()] ?? null;
            if ($key === null || $element->getLocale() !== $locale) {
                continue;
            }

            $text = $this->toText($element->getConfiguration()[$key] ?? null);
            if ($text !== null) {
                $texts[] = $text;
            }
        }

        return $texts !== [] ? implode("\n\n", $texts) : null;
    }

    private function toText(mixed $html): ?string
    {
        if (!is_string($html)) {
            return null;
        }

        // paragraphs and line breaks become line breaks, the remaining markup is removed
        $text = preg_replace('#</p>|<br\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n\s*\n+/", "\n", $text) ?? $text) ?? $text);

        return $text !== '' ? $text : null;
    }
}
