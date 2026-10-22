<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Partner;
use App\Enum\PartnerType;
use App\Form\DataTransformer\DecimalTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Fiche associé (M10). */
final class PartnerFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, ['label' => 'Code associé'])
            ->add('name', TextType::class, ['label' => 'Nom / raison sociale'])
            ->add('partnerType', EnumType::class, [
                'class' => PartnerType::class,
                'label' => 'Forme juridique',
                'choice_label' => static fn (PartnerType $type): string => $type->label(),
            ])
            ->add('ownershipPercent', TextType::class, [
                'label' => 'Part du capital (%)',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('email', TextType::class, ['label' => 'Adresse électronique', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('address', TextType::class, ['label' => 'Adresse', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'Associé actif', 'required' => false])
            ->add('notes', TextareaType::class, ['label' => 'Observations', 'required' => false, 'attr' => ['rows' => 2]]);

        $builder->get('ownershipPercent')->addModelTransformer(new DecimalTransformer(4));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Partner::class]);
    }
}
