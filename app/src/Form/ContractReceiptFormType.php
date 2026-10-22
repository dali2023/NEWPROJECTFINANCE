<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContractReceipt;
use App\Enum\ReceiptType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Réception provisoire ou définitive (§15.5).
 */
final class ContractReceiptFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('receiptType', EnumType::class, [
                'class' => ReceiptType::class,
                'label' => 'Type de réception',
                'choice_label' => static fn (ReceiptType $type): string => $type->label(),
            ])
            ->add('plannedDate', DateType::class, [
                'label' => 'Date prévisionnelle',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('actualDate', DateType::class, [
                'label' => 'Date effective',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Une réception effective doit être justifiée par un procès-verbal et validée.',
            ])
            ->add('pvReference', TextType::class, [
                'label' => 'Référence du procès-verbal',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('warrantyStartDate', DateType::class, [
                'label' => 'Début de garantie',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('warrantyEndDate', DateType::class, [
                'label' => 'Fin de garantie',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContractReceipt::class,
        ]);
    }
}
