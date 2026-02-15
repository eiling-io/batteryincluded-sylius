<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller;

use EilingIo\SyliusBatteryIncludedPlugin\Form\Type\ConfigurationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

class AdminConfigurationController extends AbstractController
{
    /**
     * @Route("/admin/batteryincluded/configuration", name="batteryincluded_admin_configuration")
     */
    public function configure(Request $request): Response
    {
        $configFile = $this->getParameter(
                'kernel.project_dir'
            ) . '/config/packages/eiling_io_sylius_battery_included.yaml';
        $data = [];
        if (file_exists($configFile)) {
            $data = Yaml::parseFile($configFile)['eiling_io_sylius_battery_included'] ?? [];
        }

        $form = $this->createForm(ConfigurationType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newData = ['eiling_io_sylius_battery_included' => $form->getData()];
            file_put_contents($configFile, Yaml::dump($newData, 4));
            $this->addFlash('success', 'Konfiguration gespeichert!');
            return $this->redirectToRoute('batteryincluded_admin_configuration');
        }

        return $this->render('@EilingIoSyliusBatteryIncludedPlugin/admin/configuration.html.twig', [
            'form' => $form->createView(),
            'resources' => null,
            'metadata' => [
                'name' => 'Battery Included',
                'applicationName' => 'applicationName',
                'version' => '1.0.0',
                'pluralName' => 'applicationNames',
            ],
        ]);
    }
}
