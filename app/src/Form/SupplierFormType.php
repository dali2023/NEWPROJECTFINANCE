<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Supplier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fiche fournisseur (§4.2).
 */
final class SupplierFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code fournisseur',
                'help' => 'Majuscules, chiffres et tirets — unique (ex. FRN-2026-001).',
                'attr' => ['maxlength' => 30, 'autocomplete' => 'off'],
            ])
            ->add('name', TextType::class, [
                'label' => 'Raison sociale',
                'attr' => ['maxlength' => 200],
            ])
            ->add('taxIdentifier', TextType::class, [
                'label' => 'Identifiant fiscal',
                'required' => false,
            ])
            ->add('serviceCategory', TextType::class, [
                'label' => 'Catégorie de service ou de fourniture',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse électronique',
                'required' => false,
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
            ])
            ->add('paymentTermsDays', IntegerType::class, [
                'label' => 'Délai de paiement (jours)',
                'attr' => ['min' => 0, 'max' => 365],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Fournisseur actif',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Supplier::class,
        ]);
    }
}
