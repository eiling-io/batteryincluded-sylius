<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('collection', TextType::class, [
                'label' => 'batteryincluded_plugin.form.collection',
                'required' => false,
            ])
            ->add('api_key', TextType::class, [
                'label' => 'batteryincluded_plugin.form.api_key',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
