<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

/**
 * Étape 1 de l'import : dépôt du classeur ou du fichier CSV.
 */
final class ProspectImportUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Fichier Excel ou CSV',
            'help' => 'La première ligne doit contenir les en-têtes de colonnes. Taille maximale : 10 Mo, 5 000 lignes.',
            'constraints' => [
                new File(
                    maxSize: '10M',
                    extensions: ['xlsx', 'xls', 'csv'],
                    extensionsMessage: 'Formats acceptés : .xlsx, .xls et .csv.',
                    maxSizeMessage: 'Le fichier ne peut pas dépasser {{ limit }} {{ suffix }}.',
                ),
            ],
            'attr' => [
                'accept' => '.xlsx,.xls,.csv',
            ],
        ]);
    }
}
