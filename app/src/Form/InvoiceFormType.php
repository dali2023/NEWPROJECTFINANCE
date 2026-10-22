<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\Contract;
use App\Entity\Client;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\ClientRepository;
use App\Repository\ContractRepository;
use App\Repository\ProjectRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Facture client (M04). */
final class InvoiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('invoiceType', EnumType::class, [
                'class' => \App\Enum\InvoiceType::class,
                'label' => 'Nature du document',
                'choice_label' => static fn (\App\Enum\InvoiceType $type): string => $type->label(),
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'label' => 'Client',
                'choice_label' => static fn (Client $client): string => $client->getName(),
                'query_builder' => static fn (ClientRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.deletedAt IS NULL')
                    ->andWhere('c.isActive = true')
                    ->orderBy('c.name', 'ASC'),
                'placeholder' => 'Sélectionner un client',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'label' => 'Projet',
                'required' => false,
                'choice_label' => static fn (Project $project): string => $project->getCode().' — '.$project->getName(),
                'query_builder' => static fn (ProjectRepository $repository) => $repository->createQueryBuilder('p')
                    ->where('p.deletedAt IS NULL')
                    ->orderBy('p.code', 'ASC'),
                'placeholder' => 'Aucun projet',
            ])
            ->add('contract', EntityType::class, [
                'class' => Contract::class,
                'label' => 'Contrat',
                'required' => false,
                'choice_label' => static fn (Contract $contract): string => $contract->getReference().' — '.$contract->getTitle(),
                'query_builder' => static fn (ContractRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.deletedAt IS NULL')
                    ->orderBy('c.reference', 'DESC'),
                'placeholder' => 'Aucun contrat',
            ])
            ->add('issueDate', DateType::class, [
                'label' => 'Date de facture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Date d’échéance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('currencyCode', TextType::class, [
                'label' => 'Devise',
                'attr' => ['maxlength' => 3],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('lines', CollectionType::class, [
                'label' => 'Lignes de facture',
                'entry_type' => InvoiceLineFormType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Invoice::class]);
    }
}
