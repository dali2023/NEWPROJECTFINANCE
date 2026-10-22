<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contract;
use App\Entity\Expense;
use App\Entity\Project;
use App\Entity\Supplier;
use App\Enum\ExpenseCategory;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\ContractRepository;
use App\Repository\ProjectRepository;
use App\Repository\SupplierRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Charge ou dépense (M05). */
final class ExpenseFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EnumType::class, [
                'class' => ExpenseCategory::class,
                'label' => 'Nature de la charge',
                'choice_label' => static fn (ExpenseCategory $category): string => $category->label(),
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['maxlength' => 200],
            ])
            ->add('supplier', EntityType::class, [
                'class' => Supplier::class,
                'label' => 'Fournisseur',
                'required' => false,
                'choice_label' => static fn (Supplier $supplier): string => $supplier->getName(),
                'query_builder' => static fn (SupplierRepository $repository) => $repository->createQueryBuilder('s')
                    ->where('s.deletedAt IS NULL')
                    ->orderBy('s.name', 'ASC'),
                'placeholder' => 'Aucun fournisseur',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'label' => 'Projet imputé',
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
            ->add('expenseDate', DateType::class, [
                'label' => 'Date de la charge',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Date d’échéance',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amountHt', TextType::class, [
                'label' => 'Montant HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('currencyCode', TextType::class, [
                'label' => 'Devise',
                'attr' => ['maxlength' => 3],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Justification / observations',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);

        $builder->get('amountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Expense::class]);
    }
}
