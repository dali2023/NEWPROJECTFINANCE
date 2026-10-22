<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContractMilestone;
use App\Entity\User;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\MilestoneType;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Échéance contractuelle de facturation (§15.6).
 */
final class ContractMilestoneFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('milestoneType', EnumType::class, [
                'class' => MilestoneType::class,
                'label' => 'Type d’événement déclencheur',
                'choice_label' => static fn (MilestoneType $type): string => $type->label(),
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['maxlength' => 200],
            ])
            ->add('plannedDate', DateType::class, [
                'label' => 'Date contractuelle prévue',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('actualDate', DateType::class, [
                'label' => 'Date effective de réalisation',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amountHt', TextType::class, [
                'label' => 'Montant prévu HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'Taux de TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('completionStatus', EnumType::class, [
                'class' => CompletionStatus::class,
                'label' => 'Statut de réalisation',
                'choice_label' => static fn (CompletionStatus $status): string => $status->label(),
            ])
            ->add('billingStatus', EnumType::class, [
                'class' => ContractBillingStatus::class,
                'label' => 'Statut de facturation',
                'choice_label' => static fn (ContractBillingStatus $status): string => $status->label(),
            ])
            ->add('responsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable de la facturation',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Responsable du contrat',
            ])
            ->add('conditions', TextareaType::class, [
                'label' => 'Conditions préalables à la facturation',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Commentaire / justificatif',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);

        $builder->get('amountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContractMilestone::class,
        ]);
    }
}
