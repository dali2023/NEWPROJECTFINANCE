<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BankAccount;
use App\Form\DataTransformer\DecimalTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Compte bancaire ou caisse (M09). */
final class BankAccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Libellé du compte'])
            ->add('bankName', TextType::class, ['label' => 'Banque', 'required' => false])
            ->add('accountNumber', TextType::class, ['label' => 'Numéro de compte', 'required' => false])
            ->add('iban', TextType::class, ['label' => 'IBAN / RIB', 'required' => false])
            ->add('currencyCode', TextType::class, ['label' => 'Devise', 'attr' => ['maxlength' => 3]])
            ->add('openingBalance', TextType::class, [
                'label' => 'Solde d’ouverture',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('openingDate', DateType::class, [
                'label' => 'Date d’ouverture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('isActive', CheckboxType::class, ['label' => 'Compte actif', 'required' => false])
            ->add('notes', TextareaType::class, ['label' => 'Observations', 'required' => false, 'attr' => ['rows' => 2]]);

        $builder->get('openingBalance')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BankAccount::class]);
    }
}
