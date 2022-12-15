<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\QueryBuilder;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }


    public function configureFields(string $pageName): iterable
    {

        yield EmailField::new('email');
        yield AvatarField::new('avatar')
            ->formatValue(static function ($value, ?User $user) {
                return $user?->getAvatarUrl();
            })
            ->hideOnForm();
        yield ImageField::new('avatar')
            ->setBasePath('uploads/avatars')
            ->setUploadDir('public/uploads/avatars')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->onlyOnForms();
        yield TextField::new('firstName')->onlyOnForms();
        yield TextField::new('lastName')->onlyOnForms();
        //yield TextField::new('fullName')->onlyOnIndex();
        yield TextField::new('fullName')->hideOnForm();

        $roles = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_MODERATOR', 'ROLE_USER'];
        yield ChoiceField::new('roles')
            ->setChoices(array_combine($roles, $roles))
            ->allowMultipleChoices()
            ->setHelp('Available roles: ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_MODERATOR, ROLE_USER')
            ->renderExpanded()
            ->renderAsBadges();

        yield BooleanField::new('enabled');
            //->renderAsSwitch(false);
        yield DateField::new('createdAt')->hideOnForm();
        yield DateField::new('updatedAt')->hideOnForm();
        /*return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];*/
    }


    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->showEntityActionsInlined()
            ->setEntityPermission('ADMIN_USER_EDIT') // ADMIN_USER_EDIT is a custom permission not is a ROLE
            ;
    }

    /*
     * Imagine that we have a lot of users - like thousands - which is pretty realistic.
     * And our user is ID 500. In that case, you would actually see many pages of results here.
     * And our user might be on page 200. So you'd see no results on page one... or two... or three... until finally,
     * on page 200, you'd find our one result. So it can get a little weird if you have many items in an admin section,
     * and many of them are hidden. To fix this, we can modify the query that's made for the index page to only return
     * the users we want. This is totally optional, but can make for a better user experience.
     */
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $queryBuilder;
        }

        $queryBuilder
            ->andWhere('entity.id = :id')
            ->setParameter('id', $this->getUser()->getId());

        return $queryBuilder;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add(BooleanFilter::new('enabled')
                    ->setFormTypeOption('expanded', false)
            );

    }


}
