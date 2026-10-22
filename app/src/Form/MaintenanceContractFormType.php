<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contract;
use App\Entity\MaintenanceContract;
use App\Entity\User;
use App\Enum\BillingFrequency;
use App\Enum\MaintenanceRenewalStatus;
use App\Enum\RenewalMode;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\ContractRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Contrat de maintenance (§15.3).
 */
final class MaintenanceContractFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originalContract', EntityType::class, [
                'class' => Contract::class,
                'label' => 'Contrat initial / avenant associé',
                'required' => false,
                'choice_label' => static fn (Contract $contract): string => $contract->getReference().' — '.$contract->getTitle(),
                'query_builder' => static fn (ContractRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.deletedAt IS NULL')
                    ->orderBy('c.reference', 'DESC'),
                'placeholder' => 'Aucun contrat initial',
            ])
            ->add('maintenanceStartDate', DateType::class, [
                'label' => 'Début de maintenance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('maintenanceEndDate', DateType::class, [
                'label' => 'Fin de maintenance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('durationMonths', IntegerType::class, [
                'label' => 'Durée (mois)',
                'attr' => ['min' => 1],
            ])
            ->add('billingFrequency', EnumType::class, [
                'class' => BillingFrequency::class,
                'label' => 'Périodicité',
                'choice_label' => static fn (BillingFrequency $frequency): string => $frequency->label(),
            ])
            ->add('periodicAmountHt', TextType::class, [
                'label' => 'Montant périodique HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('annualAmountHt', TextType::class, [
                'label' => 'Montant annuel HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
                'help' => 'Laissé à zéro, il est calculé à partir du montant périodique.',
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'Taux de TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('renewalNoticeDays', IntegerType::class, [
                'label' => 'Préavis de renouvellement (jours)',
                'attr' => ['min' => 0],
            ])
            ->add('renewalMode', EnumType::class, [
                'class' => RenewalMode::class,
                'label' => 'Mode de renouvellement',
                'choice_label' => static fn (RenewalMode $mode): string => $mode->label(),
            ])
            ->add('renewalStatus', EnumType::class, [
                'class' => MaintenanceRenewalStatus::class,
                'label' => 'Statut de renouvellement',
                'choice_label' => static fn (MaintenanceRenewalStatus $status): string => $status->label(),
            ])
            ->add('commercialResponsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable commercial',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
            ])
            ->add('technicalResponsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable technique',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
            ])
            ->add('priceRevisionTerms', TextareaType::class, [
                'label' => 'Conditions de révision du prix',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);

        $builder->get('periodicAmountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('annualAmountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaintenanceContract::class,
        ]);
    }
}
