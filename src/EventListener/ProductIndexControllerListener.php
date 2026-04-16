<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\EventListener;

use EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop\TaxonController;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

final class ProductIndexControllerListener
{
    public function __construct(
        private readonly TaxonController $taxonController,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_route') !== 'sylius_shop_product_index') {
            return;
        }

        $event->setController([$this->taxonController, 'index']);
    }
}
