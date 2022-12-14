<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

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
        yield Field::new('name');
        yield AssociationField::new('topic');
        yield TextareaField::new('question')
            ->hideOnIndex();
        yield Field::new('votes', 'Total Votes')
            ->setTextAlign('center');
        yield AssociationField::new('askedBy')
            ->formatValue(static function ($value, Question $question): ?string {
                if (!$user = $question->getAskedBy()) {
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
        yield Field::new('createdAt')
            ->hideOnForm();
        yield Field::new('updatedAt')
            ->hideOnForm();
    }

}
