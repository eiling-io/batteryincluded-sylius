<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Cms;

use Doctrine\ORM\EntityRepository;
use EilingIo\SyliusBatteryIncludedPlugin\Dto\ContentPageDto;
use Sylius\CmsPlugin\Entity\PageInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

/**
 * Loads the CMS pages of search hits, like the products are loaded by their ordernumber. Only registered if the
 * Sylius CMS plugin is installed and enabled, the controllers search content pages only if it exists.
 */
final readonly class CmsPageFinder
{
    /**
     * @param EntityRepository<PageInterface> $pageRepository
     */
    public function __construct(
        private EntityRepository $pageRepository,
        private ChannelContextInterface $channelContext,
    ) {
    }

    /**
     * Codes of the content pages in the hits of a browse or the documents of a suggest response.
     *
     * @param array<mixed> $documents documents or hits with `document`
     *
     * @return list<string>
     */
    public function getCodes(array $documents): array
    {
        $codes = [];
        foreach ($documents as $document) {
            $code = ($document['document'] ?? $document)['_' . ContentPageDto::TYPE]['code'] ?? null;
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Enabled and published pages of the current channel, in the order of the codes.
     *
     * @param list<string> $codes
     *
     * @return list<PageInterface>
     */
    public function findByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        /** @var list<PageInterface> $pages */
        $pages = $this->pageRepository->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('o.enabled = :enabled')
            ->andWhere('o.publishAt IS NULL OR o.publishAt <= :now')
            ->andWhere('o.code IN (:codes)')
            ->setParameter('channel', $this->channelContext->getChannel())
            ->setParameter('enabled', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getResult();

        $position = array_flip($codes);
        usort($pages, static fn (PageInterface $a, PageInterface $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);

        return $pages;
    }
}
