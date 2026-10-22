<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MailSettings;
use App\Enum\MailEncryption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Paramètres SMTP utilisés pour les emails de rappel.
 */
final class MailSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isEnabled', CheckboxType::class, [
                'label' => 'Activer l’envoi automatique des rappels par email',
                'required' => false,
            ])
            ->add('host', TextType::class, [
                'label' => 'Serveur SMTP',
                'attr' => ['maxlength' => 255, 'placeholder' => 'smtp.exemple.tn'],
            ])
            ->add('port', IntegerType::class, [
                'label' => 'Port',
                'attr' => ['min' => 1, 'max' => 65535],
                'help' => '587 pour STARTTLS, 465 pour TLS implicite, 25 pour un relais interne.',
            ])
            ->add('encryption', EnumType::class, [
                'class' => MailEncryption::class,
                'label' => 'Chiffrement de la connexion',
                'choice_label' => static fn (MailEncryption $mode): string => $mode->label(),
            ])
            ->add('username', TextType::class, [
                'label' => 'Utilisateur SMTP',
                'required' => false,
                'attr' => ['maxlength' => 180, 'autocomplete' => 'off'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe SMTP',
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => 'Laisser vide pour conserver le mot de passe enregistré. Il est chiffré en base et n’est jamais réaffiché.',
            ])
            ->add('clearPassword', CheckboxType::class, [
                'label' => 'Supprimer le mot de passe enregistré',
                'mapped' => false,
                'required' => false,
            ])
            ->add('senderEmail', EmailType::class, [
                'label' => 'Adresse d’expédition',
                'attr' => ['maxlength' => 180, 'placeholder' => 'no-reply@exemple.tn'],
            ])
            ->add('senderName', TextType::class, [
                'label' => 'Nom de l’expéditeur',
                'attr' => ['maxlength' => 180],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailSettings::class,
        ]);
    }
}