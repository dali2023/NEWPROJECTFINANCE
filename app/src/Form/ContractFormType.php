<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contract;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ContractStatus;
use App\Enum\ContractType;
use App\Enum\RenewalMode;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
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
 * Fiche contrat — informations générales et conditions financières (§15.2).
 */
final class ContractFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Intitulé du contrat',
                'attr' => ['maxlength' => 200],
            ])
            ->add('contractType', EnumType::class, [
                'class' => ContractType::class,
                'label' => 'Type de contrat',
                'choice_label' => static fn (ContractType $type): string => $type->label(),
            ])
            ->add('client', EntityType::class, [
                'class' => \App\Entity\Client::class,
                'label' => 'Client / organisme bénéficiaire',
                'choice_label' => static fn ($client): string => $client->getCode().' — '.$client->getName(),
                'query_builder' => static fn (ClientRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.isActive = true')
                    ->andWhere('c.deletedAt IS NULL')
                    ->orderBy('c.name', 'ASC'),
                'placeholder' => 'Sélectionner un client',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'label' => 'Projet associé',
                'required' => false,
                'choice_label' => static fn (Project $project): string => $project->getCode().' — '.$project->getName(),
                'query_builder' => static fn (ProjectRepository $repository) => $repository->createQueryBuilder('p')
                    ->where('p.deletedAt IS NULL')
                    ->orderBy('p.code', 'DESC'),
                'placeholder' => 'Aucun projet associé',
            ])
            ->add('marketReference', TextType::class, [
                'label' => 'Numéro de marché ou de commande',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('signatureDate', DateType::class, [
                'label' => 'Date de signature',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('effectiveStartDate', DateType::class, [
                'label' => 'Date de prise d’effet',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('executionStartDate', DateType::class, [
                'label' => 'Date de début d’exécution',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('effectiveEndDate', DateType::class, [
                'label' => 'Date de fin contractuelle',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amountHt', TextType::class, [
                'label' => 'Montant HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'Taux de TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('currencyCode', TextType::class, [
                'label' => 'Devise',
                'attr' => ['maxlength' => 3],
            ])
            ->add('responsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable interne',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
            ])
            ->add('paymentTerms', TextType::class, [
                'label' => 'Conditions de paiement',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('renewalMode', EnumType::class, [
                'class' => RenewalMode::class,
                'label' => 'Mode de renouvellement',
                'choice_label' => static fn (RenewalMode $mode): string => $mode->label(),
                'help' => 'Le mode automatique décrit une clause ; il ne déclenche jamais un renouvellement sans action validée.',
            ])
            ->add('noticePeriodDays', IntegerType::class, [
                'label' => 'Préavis contractuel (jours)',
                'attr' => ['min' => 0],
                'help' => 'La date de préparation est calculée : fin contractuelle − préavis.',
            ])
            ->add('warrantyDurationDays', IntegerType::class, [
                'label' => 'Durée de garantie (jours)',
                'attr' => ['min' => 0],
                'help' => 'Sert au calcul indicatif de la réception définitive.',
            ])
            ->add('renewalTerms', TextareaType::class, [
                'label' => 'Conditions de renouvellement',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('status', EnumType::class, [
                'class' => ContractStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (ContractStatus $status): string => $status->label(),
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);

        $builder->get('amountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contract::class,
        ]);
    }
}
