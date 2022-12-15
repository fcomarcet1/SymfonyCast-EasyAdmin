<?php

namespace App\Controller\Admin;

use App\EasyAdmin\VotesField;
use App\Entity\Question;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;



#[IsGranted('ROLE_MODERATOR')]
class QuestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();
        yield Field::new('name')
            ->setSortable(false); //Disabling Sorting on a Field
        yield Field::new('slug')
            ->hideOnIndex()
            // on the edit page... it's disabled. And if we go back to Questions... and create a new question...
            //  we have a not disabled slug field!
            ->setFormTypeOption(
                'disabled',
                $pageName !== Crud::PAGE_NEW
            );;
        yield AssociationField::new('topic');
        /*yield TextEditorField::new('question')
            ->hideOnIndex();*/
        yield TextareaField::new('question')
            ->hideOnIndex()
            ->setFormTypeOptions([
                'row_attr' => [
                    'data-controller' => 'snarkdown',
                ],
                'attr' => [
                    'data-snarkdown-target' => 'input',
                    'data-action' => 'snarkdown#render',
                ],
            ])
            ->setHelp('Preview:');
       /* yield IntegerField::new('votes', 'Total Votes')
            ->setTextAlign('center')
            ->setTemplatePath('admin/field/votes.html.twig');*/
        yield VotesField::new('votes', 'Total Votes')
            ->setTextAlign('center')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield AssociationField::new('askedBy')
            ->formatValue(static function ($value, ?Question $question): ?string {
                if (!$user = $question?->getAskedBy()) {
                    return null;
                }
                return sprintf('%s&nbsp;(%s)', $user->getEmail(), $user->getQuestions()->count());
            })
            // Let's restrict this to only enabled users
            ->setQueryBuilder(function (QueryBuilder $qb) {
                $qb->andWhere('entity.enabled = :enabled')
                    ->setParameter('enabled', true);
            })
            // in this case, every user in the database, is loaded onto the page in the background to build the
            // select. This means that if you have even a hundred users in your database,
            // this page is going to start slowing down, and eventually, explode. we can use ->autocomplete()
            ->autocomplete();

        yield AssociationField::new('answers')
            //->setFormTypeOption('choice_label', 'id')
            //setting by_reference to false, if an answer is removed from this question, it will force the system to
            // call the removeAnswer() method that I have in Question
            ->setFormTypeOption('by_reference', false)
            ->setTextAlign('center')
            ->autocomplete();

        yield Field::new('createdAt')
            ->hideOnForm();

        yield Field::new('updatedAt')
            ->hideOnForm();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort([
                'askedBy.enabled' => 'DESC',
                'createdAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::INDEX, 'ROLE_MODERATOR')
            ->setPermission(Action::DETAIL, 'ROLE_MODERATOR')
            ->setPermission(Action::EDIT, 'ROLE_MODERATOR')
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('topic')
            ->add('createdAt')
            ->add('votes')
            ->add('name');
    }


}
