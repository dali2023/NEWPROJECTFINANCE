<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\ContractRenewalData;
use App\Entity\User;
use App\Enum\RenewalDecisionStatus;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enregistrement d'un renouvellement : crée le nouveau contrat et le relie à
 * l'ancien (§15.4, REC-024).
 */
final class ContractRenewalFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newStartDate', DateType::class, [
                'label' => 'Nouvelle date de début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('newEndDate', DateType::class, [
                'label' => 'Nouvelle date de fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('newAmountHt', TextType::class, [
                'label' => 'Nouveau montant HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('decisionStatus', EnumType::class, [
                'class' => RenewalDecisionStatus::class,
                'label' => 'Décision du client',
                'choice_label' => static fn (RenewalDecisionStatus $status): string => $status->label(),
            ])
            ->add('responsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable du renouvellement',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Responsable du contrat',
            ])
            ->add('generateMilestones', CheckboxType::class, [
                'label' => 'Générer les échéances de la nouvelle période',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes de négociation',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);

        $builder->get('newAmountHt')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContractRenewalData::class,
        ]);
    }
}
