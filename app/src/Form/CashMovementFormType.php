<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BankAccount;
use App\Entity\CashMovement;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\BankAccountRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Mouvement manuel de trésorerie (M09). */
final class CashMovementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bankAccount', EntityType::class, [
                'class' => BankAccount::class,
                'label' => 'Compte',
                'choice_label' => static fn (BankAccount $account): string => $account->getName(),
                'query_builder' => static fn (BankAccountRepository $repository) => $repository->createQueryBuilder('b')
                    ->where('b.isActive = true')
                    ->orderBy('b.name', 'ASC'),
                'placeholder' => 'Sélectionner un compte',
            ])
            ->add('movementDate', DateType::class, [
                'label' => 'Date du mouvement',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('direction', EnumType::class, [
                'class' => TreasuryDirection::class,
                'label' => 'Sens',
                'choice_label' => static fn (TreasuryDirection $direction): string => $direction->label(),
            ])
            ->add('movementType', EnumType::class, [
                'class' => TreasuryMovementType::class,
                'label' => 'Origine',
                'choice_label' => static fn (TreasuryMovementType $type): string => $type->label(),
            ])
            ->add('amount', TextType::class, [
                'label' => 'Montant',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('label', TextType::class, ['label' => 'Libellé'])
            ->add('notes', TextareaType::class, ['label' => 'Observations', 'required' => false, 'attr' => ['rows' => 2]]);

        $builder->get('amount')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CashMovement::class]);
    }
}
