<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Prospect;
use App\Entity\User;
use App\Enum\ProspectStatus;
use App\Form\DataTransformer\DecimalTransformer;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fiche prospect — identité, qualification et suivi commercial.
 */
final class ProspectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Raison sociale',
                'attr' => ['maxlength' => 200],
            ])
            ->add('contactName', TextType::class, [
                'label' => 'Contact principal',
                'required' => false,
                'attr' => ['maxlength' => 160],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse électronique',
                'required' => false,
                'attr' => ['maxlength' => 180],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['maxlength' => 40],
            ])
            ->add('taxIdentifier', TextType::class, [
                'label' => 'Identifiant fiscal',
                'required' => false,
                'attr' => ['maxlength' => 60],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['maxlength' => 120],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['maxlength' => 20],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => ['maxlength' => 80],
            ])
            ->add('source', TextType::class, [
                'label' => 'Source / origine',
                'required' => false,
                'help' => 'Ex. salon, recommandation, site web, appel d’offres, prospection directe.',
                'attr' => ['maxlength' => 80],
            ])
            ->add('status', EnumType::class, [
                'class' => ProspectStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (ProspectStatus $status): string => $status->label(),
                'choice_filter' => static fn (ProspectStatus $status): bool => !$status->isConverted(),
                'help' => 'Le statut « Converti » est posé automatiquement par la conversion.',
            ])
            ->add('owner', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable du suivi',
                'required' => false,
                'choice_label' => static fn (User $user): string => $user->getFullName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->where('u.isActive = true')
                    ->orderBy('u.fullName', 'ASC'),
                'placeholder' => 'Non affecté',
            ])
            ->add('estimatedAmountHt', TextType::class, [
                'label' => 'Montant estimé HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('probability', IntegerType::class, [
                'label' => 'Probabilité de conclusion (%)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
            ])
            ->add('expectedCloseDate', DateType::class, [
                'label' => 'Date de conclusion prévue',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('nextAction', TextType::class, [
                'label' => 'Prochaine action',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('nextActionDate', DateType::class, [
                'label' => 'Date de la prochaine action',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Commentaires',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);

        $builder->get('estimatedAmountHt')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prospect::class,
        ]);
    }
}
