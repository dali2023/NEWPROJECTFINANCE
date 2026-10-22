<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte utilisateur (§3, §7.12). Le mot de passe n'est jamais affiché : seule
 * une nouvelle saisie permet de le définir ou de le remplacer.
 */
final class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['maxlength' => 160],
            ])
            ->add('username', TextType::class, [
                'label' => 'Nom de connexion',
                'attr' => ['maxlength' => 60, 'autocomplete' => 'off'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse électronique',
            ])
            ->add('assignedRoles', EntityType::class, [
                'class' => Role::class,
                'label' => 'Rôles',
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => true,
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('r')->orderBy('r.label', \SortDirection::Ascending),
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $isNew,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmation du mot de passe'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => $isNew
                    ? [new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'), $this->passwordConstraint()]
                    : [$this->passwordConstraint()],
                'attr' => ['autocomplete' => 'new-password'],
            ]);
    }

    private function passwordConstraint(): Assert\Length
    {
        return new Assert\Length(
            min: 8,
            max: 120,
            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => true,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
