<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\ProspectConversionData;
use App\Entity\ProjectType;
use App\Entity\User;
use App\Enum\ClientType;
use App\Enum\ContractType;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\ProjectTypeRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Paramètres de création du client, du projet et du contrat brouillon.
 */
final class ProspectConversionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientType', EnumType::class, [
                'class' => ClientType::class,
                'label' => 'Type de client',
                'choice_label' => static fn (ClientType $type): string => $type->label(),
            ])
            ->add('paymentTermsDays', IntegerType::class, [
                'label' => 'Délai de paiement (jours)',
                'attr' => ['min' => 0, 'max' => 365],
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
            ->add('projectManager', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable du projet',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
            ])
            ->add('projectName', TextType::class, [
                'label' => 'Intitulé du projet',
                'attr' => ['maxlength' => 200],
            ])
            ->add('contractTitle', TextType::class, [
                'label' => 'Intitulé du contrat',
                'attr' => ['maxlength' => 200],
            ])
            ->add('contractType', EnumType::class, [
                'class' => ContractType::class,
                'label' => 'Type de contrat',
                'choice_label' => static fn (ContractType $type): string => $type->label(),
            ])
            ->add('contractAmountHt', TextType::class, [
                'label' => 'Montant du contrat HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('vatRate', TextType::class, [
                'label' => 'Taux de TVA (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('currencyCode', TextType::class, [
                'label' => 'Devise',
                'attr' => ['maxlength' => 3],
            ]);

        $builder->get('contractAmountHt')->addModelTransformer(new DecimalTransformer());
        $builder->get('vatRate')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProspectConversionData::class,
        ]);
    }
}
