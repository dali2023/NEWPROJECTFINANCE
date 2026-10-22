<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Attribution des permissions à un rôle (§3.1, §7.12).
 *
 * Les permissions sont présentées par module fonctionnel ; l'enregistrement
 * d'une modification est journalisé (permission_change).
 */
final class RolePermissionsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('permissions', EntityType::class, [
            'class' => Permission::class,
            'label' => 'Permissions autorisées',
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choice_label' => static fn (Permission $permission): string => $permission->getLabel().'  ('.$permission->getCode().')',
            'group_by' => static fn (Permission $permission): string => $permission->getModule() ?? 'Général',
            'query_builder' => static fn (PermissionRepository $repository) => $repository->createQueryBuilder('p')
                ->orderBy('p.module', 'ASC')
                ->addOrderBy('p.code', 'ASC'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\Role::class,
        ]);
    }
}
