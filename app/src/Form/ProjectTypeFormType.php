<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProjectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Typologie des projets (§4.3).
 */
final class ProjectTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'help' => 'Majuscules et tirets bas (ex. INTEGRATION_ERP).',
                'attr' => ['maxlength' => 30],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé affiché',
                'attr' => ['maxlength' => 120],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d’affichage',
                'attr' => ['min' => 0],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Type actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectType::class,
        ]);
    }
}
