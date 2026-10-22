<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProjectMilestone;
use App\Enum\MilestoneStatus;
use App\Form\DataTransformer\DecimalTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Jalon de l'échéancier contractuel (§5.2 G).
 */
final class ProjectMilestoneFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['maxlength' => 200],
            ])
            ->add('plannedDate', DateType::class, [
                'label' => 'Date prévue',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amountHt', TextType::class, [
                'label' => 'Montant HT',
                'attr' => ['inputmode' => 'decimal', 'data-money' => '1'],
            ])
            ->add('status', EnumType::class, [
                'class' => MilestoneStatus::class,
                'label' => 'Statut',
                'choice_label' => static fn (MilestoneStatus $status): string => $status->label(),
            ]);

        $builder->get('amountHt')->addModelTransformer(new DecimalTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectMilestone::class,
        ]);
    }
}
