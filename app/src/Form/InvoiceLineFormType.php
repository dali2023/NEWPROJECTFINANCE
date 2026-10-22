<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InvoiceLine;
use App\Form\DataTransformer\DecimalTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Ligne de facture (M04). */
final class InvoiceLineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('designation', TextType::class, [
                'label' => 'Désignation',
                'attr' => ['maxlength' => 255],
            ])
            ->add('quantity', TextType::class, [
                'label' => 'Quantité',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('unitPriceHt', TextType::class, [
                'label' => 'Prix unitaire HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ]);

        $builder->get('quantity')->addModelTransformer(new DecimalTransformer());
        $builder->get('unitPriceHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InvoiceLine::class]);
    }
}
