<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContractDocument;
use App\Enum\ContractDocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Dépôt d'un document contractuel (§15.2, §15.5).
 */
final class ContractDocumentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé du document',
                'attr' => ['maxlength' => 200],
            ])
            ->add('documentType', EnumType::class, [
                'class' => ContractDocumentType::class,
                'label' => 'Nature du document',
                'choice_label' => static fn (ContractDocumentType $type): string => $type->label(),
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File(maxSize: '10M'),
                ],
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
            'data_class' => ContractDocument::class,
        ]);
    }
}
