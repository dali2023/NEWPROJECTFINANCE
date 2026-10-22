<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BankAccount;
use App\Entity\Partner;
use App\Entity\PartnerTransaction;
use App\Enum\PartnerTransactionType;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\BankAccountRepository;
use App\Repository\PartnerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Opération d'associé (M10). */
final class PartnerTransactionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('partner', EntityType::class, [
                'class' => Partner::class,
                'label' => 'Associé',
                'choice_label' => static fn (Partner $partner): string => $partner->getCode().' — '.$partner->getName(),
                'query_builder' => static fn (PartnerRepository $repository) => $repository->createQueryBuilder('p')
                    ->where('p.isActive = true')
                    ->orderBy('p.name', 'ASC'),
                'placeholder' => 'Sélectionner un associé',
            ])
            ->add('transactionType', EnumType::class, [
                'class' => PartnerTransactionType::class,
                'label' => 'Nature de l’opération',
                'choice_label' => static fn (PartnerTransactionType $type): string => $type->label(),
            ])
            ->add('transactionDate', DateType::class, [
                'label' => 'Date de l’opération',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amount', TextType::class, [
                'label' => 'Montant',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('currencyCode', TextType::class, ['label' => 'Devise', 'attr' => ['maxlength' => 3]])
            ->add('label', TextType::class, ['label' => 'Libellé'])
            ->add('bankAccount', EntityType::class, [
                'class' => BankAccount::class,
                'label' => 'Compte de règlement',
                'required' => false,
                'choice_label' => static fn (BankAccount $account): string => $account->getName(),
                'query_builder' => static fn (BankAccountRepository $repository) => $repository->createQueryBuilder('b')
                    ->where('b.isActive = true')
                    ->orderBy('b.name', 'ASC'),
                'placeholder' => 'Aucun compte',
            ])
            ->add('notes', TextareaType::class, ['label' => 'Notes', 'required' => false, 'attr' => ['rows' => 2]]);

        $builder->get('amount')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PartnerTransaction::class]);
    }
}
