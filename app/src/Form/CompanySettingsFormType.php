<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompanySettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Identité et coordonnées de la société.
 */
final class CompanySettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('legalName', TextType::class, [
                'label' => 'Raison sociale',
                'attr' => ['maxlength' => 180],
            ])
            ->add('tradeName', TextType::class, [
                'label' => 'Nom commercial',
                'required' => false,
                'attr' => ['maxlength' => 180],
                'help' => 'Facultatif. Utilisé comme nom principal dans l’interface.',
            ])
            ->add('taxId', TextType::class, [
                'label' => 'Matricule fiscal',
                'required' => false,
                'attr' => ['maxlength' => 60],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse électronique',
                'required' => false,
                'attr' => ['maxlength' => 180],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone principal',
                'required' => false,
                'attr' => ['maxlength' => 40, 'placeholder' => '+216 ...'],
            ])
            ->add('phoneSecondary', TextType::class, [
                'label' => 'Téléphone secondaire',
                'required' => false,
                'attr' => ['maxlength' => 40, 'placeholder' => '+216 ...'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Rue, immeuble, zone...'],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['maxlength' => 20],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['maxlength' => 120],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => ['maxlength' => 120],
            ])
            ->add('website', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'attr' => ['maxlength' => 255, 'placeholder' => 'https://...'],
            ])
            ->add('logo', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Le logo doit être une image PNG, JPEG ou WebP.',
                    ),
                ],
                'help' => 'PNG, JPEG ou WebP, 2 Mo maximum. Le logo remplace les initiales dans le bandeau et les impressions.',
            ])
            ->add('removeLogo', CheckboxType::class, [
                'label' => 'Supprimer le logo actuel',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompanySettings::class,
        ]);
    }
}