<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Étape 2 de l'import : correspondance explicite entre colonnes et champs.
 */
final class ProspectImportMappingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, string> $columns */
        $columns = $options['columns'];
        $choices = [];
        foreach ($columns as $index => $label) {
            $choices[$label] = $index;
        }

        $fields = [
            'name' => [
                'label' => 'Raison sociale',
                'required' => true,
                'help' => 'Obligatoire. C’est la clé d’identification principale du prospect.',
            ],
            'contactName' => ['label' => 'Contact principal', 'required' => false],
            'email' => ['label' => 'Adresse électronique', 'required' => false, 'help' => 'Utilisée en priorité pour détecter les doublons.'],
            'phone' => ['label' => 'Téléphone', 'required' => false],
            'taxIdentifier' => ['label' => 'Identifiant fiscal', 'required' => false],
            'address' => ['label' => 'Adresse', 'required' => false],
            'city' => ['label' => 'Ville', 'required' => false, 'help' => 'Avec la raison sociale, sert de clé de doublon si l’email est absent.'],
            'postalCode' => ['label' => 'Code postal', 'required' => false],
            'country' => ['label' => 'Pays', 'required' => false],
            'source' => ['label' => 'Source / origine', 'required' => false],
            'status' => [
                'label' => 'Statut',
                'required' => false,
                'help' => 'Valeurs acceptées : Nouveau, Qualifié, En discussion, Gagné, Perdu. Vide = Nouveau.',
            ],
            'estimatedAmountHt' => ['label' => 'Montant estimé HT', 'required' => false],
            'probability' => ['label' => 'Probabilité (%)', 'required' => false],
            'expectedCloseDate' => [
                'label' => 'Date de conclusion prévue',
                'required' => false,
                'help' => 'Formats acceptés : date Excel, jj/mm/aaaa ou aaaa-mm-jj.',
            ],
            'nextAction' => ['label' => 'Prochaine action', 'required' => false],
            'nextActionDate' => ['label' => 'Date de la prochaine action', 'required' => false],
            'notes' => ['label' => 'Commentaires', 'required' => false],
        ];

        foreach ($fields as $name => $definition) {
            $builder->add($name, ChoiceType::class, [
                'label' => $definition['label'],
                'choices' => $choices,
                'required' => $definition['required'],
                'placeholder' => $definition['required'] ? 'Sélectionner une colonne' : '— Ignorer —',
                'help' => $definition['help'] ?? null,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'columns' => [],
        ]);
        $resolver->setAllowedTypes('columns', 'array');
    }
}
