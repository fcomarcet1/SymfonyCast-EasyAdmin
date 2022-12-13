<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }


    public function configureFields(string $pageName): iterable
    {

        yield EmailField::new('email');
        yield TextField::new('firstName')->onlyOnForms();;
        yield TextField::new('lastName')->onlyOnForms();
        //yield TextField::new('fullName')->onlyOnIndex();
        yield TextField::new('fullName')->hideOnForm();;
        yield ArrayField::new('roles');
        yield BooleanField::new('enabled');
            //->renderAsSwitch(false);
        yield DateField::new('createdAt')->hideOnForm();;
        yield DateField::new('updatedAt')->hideOnForm();;
        /*return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];*/
    }

}
