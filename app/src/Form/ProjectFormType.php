<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Entity\ProjectType;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\ClientRepository;
use App\Repository\ProjectTypeRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fiche projet et contrat (§4.3, §7.4).
 */
final class ProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Intitulé du projet',
                'attr' => ['maxlength' => 200],
            ])
            ->add('client', EntityType::class, [
                'class' => \App\Entity\Client::class,
                'label' => 'Client',
                'choice_label' => static fn ($client): string => $client->getCode().' — '.$client->getName(),
                'query_builder' => static fn (ClientRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.isActive = true')
                    ->andWhere('c.deletedAt IS NULL')
                    ->orderBy('c.name', 'ASC'),
                'placeholder' => 'Sélectionner un client',
            ])
            ->add('projectType', EntityType::class, [
                'class' => ProjectType::class,
                'label' => 'Type de projet',
                'choice_label' => 'label',
                'query_builder' => static fn (ProjectTypeRepository $repository) => $repository->createQueryBuilder('t')
                    ->where('t.isActive = true')
                    ->orderBy('t.sortOrder', 'ASC'),
                'placeholder' => 'Sélectionner un type',
            ])
            ->add('manager', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable du projet',
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'class' => ProjectStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (ProjectStatus $status): string => $status->label(),
                'help' => 'Un projet n’est un contrat signé qu’au statut « Gagné / contrat signé » ou au-delà.',
            ])
            ->add('contractReference', TextType::class, [
                'label' => 'Numéro de marché ou de contrat',
                'required' => false,
            ])
            ->add('tenderReference', TextType::class, [
                'label' => 'Référence de l’appel d’offres',
                'required' => false,
            ])
            ->add('signedOn', DateType::class, [
                'label' => 'Date de signature',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('plannedDeliveryDate', DateType::class, [
                'label' => 'Livraison prévisionnelle',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('currencyCode', TextType::class, [
                'label' => 'Devise contractuelle',
                'attr' => ['maxlength' => 3],
            ])
            ->add('contractAmountHt', TextType::class, [
                'label' => 'Montant contractuel HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'Taux de TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('budgetAmount', TextType::class, [
                'label' => 'Budget prévisionnel des charges',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('paymentTerms', TextType::class, [
                'label' => 'Conditions de paiement',
                'required' => false,
            ])
            ->add('probability', IntegerType::class, [
                'label' => 'Probabilité de conclusion (%)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
                'help' => 'Uniquement pour une opportunité commerciale.',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Commentaires',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('milestones', CollectionType::class, [
                'label' => 'Échéancier contractuel',
                'entry_type' => ProjectMilestoneFormType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ]);

        $builder->get('contractAmountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
        $builder->get('budgetAmount')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
