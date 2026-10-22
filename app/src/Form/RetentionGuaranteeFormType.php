<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RetentionGuarantee;
use App\Enum\RetentionStatus;
use App\Form\DataTransformer\DecimalTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Retenue de garantie (§15.5).
 */
final class RetentionGuaranteeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', TextType::class, [
                'label' => 'Montant retenu',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('currencyCode', TextType::class, [
                'label' => 'Devise',
                'attr' => ['maxlength' => 3],
            ])
            ->add('eligibilityDate', DateType::class, [
                'label' => 'Date d’éligibilité à la libération',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('releaseRequestDate', DateType::class, [
                'label' => 'Date de demande de libération',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('actualReleaseDate', DateType::class, [
                'label' => 'Date de libération effective',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', EnumType::class, [
                'class' => RetentionStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (RetentionStatus $status): string => $status->label(),
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Conditions contractuelles de libération',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);

        $builder->get('amount')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RetentionGuarantee::class,
        ]);
    }
}
