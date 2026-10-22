<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\FiscalYear;
use App\Enum\FiscalYearStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Exercice financier (§4.1).
 */
final class FiscalYearFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'help' => 'Par exemple « 2026 » ou « Exercice 2026 ».',
                'attr' => ['maxlength' => 20],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date d’ouverture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de clôture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', EnumType::class, [
                'class' => FiscalYearStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (FiscalYearStatus $status): string => $status->label(),
                'help' => 'La clôture définitive s’effectue depuis la liste des exercices, avec traçabilité.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FiscalYear::class,
        ]);
    }
}
