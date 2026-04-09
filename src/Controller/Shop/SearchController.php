<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function search(): Response
    {
        // Hier kannst du beliebige Logik einbauen
        return $this->render('@EilingIoSyliusBatteryIncludedPlugin/shop/search/search.html.twig', [
            'message' => 'Hello from your custom Shop-Controller!'
        ]);
    }
}

