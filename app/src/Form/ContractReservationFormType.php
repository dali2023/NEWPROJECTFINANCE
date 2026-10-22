<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContractReservation;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Réserve constatée lors d'une réception (§15.5).
 */
final class ContractReservationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description de la réserve',
                'attr' => ['rows' => 3],
            ])
            ->add('dateIdentified', DateType::class, [
                'label' => 'Date de constat',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('plannedResolutionDate', DateType::class, [
                'label' => 'Date limite de levée',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('actualResolutionDate', DateType::class, [
                'label' => 'Date de levée effective',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', EnumType::class, [
                'class' => ReservationStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (ReservationStatus $status): string => $status->label(),
            ])
            ->add('responsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable du suivi',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContractReservation::class,
        ]);
    }
}
