<?php

namespace App\Controller\Admin;

use App\EasyAdmin\VotesField;
use App\Entity\Question;
use App\Entity\User;
use App\Service\CsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;


#[IsGranted('ROLE_MODERATOR')]
class QuestionCrudController extends AbstractCrudController
{

    private AdminUrlGenerator $adminUrlGenerator;
    private RequestStack $requestStack;
    public function __construct(AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }


    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();
        yield FormField::addPanel('Basic Data')
            ->collapsible()
            ->setIcon('fas fa-info-circle');
        yield Field::new('name')
            ->setSortable(false) //Disabling Sorting on a Field
            ->setColumns(5)
        ;
        yield Field::new('slug')
            ->hideOnIndex()
            // on the edit page... it's disabled. And if we go back to Questions... and create a new question...
            //  we have a not disabled slug field!
            ->setFormTypeOption(
                'disabled',
                $pageName !== Crud::PAGE_NEW
            )
            ->setColumns(5)
        ;
        yield AssociationField::new('topic');
        /*yield TextEditorField::new('question')
            ->hideOnIndex();*/
        yield TextareaField::new('question')
            ->hideOnIndex()
            ->setFormTypeOptions([
                'row_attr' => [
                    'data-controller' => 'snarkdown',
                ],
                'attr'     => [
                    'data-snarkdown-target' => 'input',
                    'data-action'           => 'snarkdown#render',
                ],
            ])
            ->setHelp('Preview:');
        /* yield IntegerField::new('votes', 'Total Votes')
             ->setTextAlign('center')
             ->setTemplatePath('admin/field/votes.html.twig');*/
        yield VotesField::new('votes', 'Total Votes')
            ->setTextAlign('center')
            ->setPermission('ROLE_SUPER_ADMIN');

        yield FormField::addPanel('Details')
            ->collapsible()
            ->setIcon('fa fa-info')
            ->setHelp('Additional Details');

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
            ->onlyOnDetail()
            ->hideOnForm();

        yield AssociationField::new('updatedBy')
            ->onlyOnDetail();

    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort([
                'askedBy.enabled' => 'DESC',
                'createdAt'       => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        /*$viewAction = Action::new('view')
            ->linkToUrl(function(Question $question) {
                return $this->generateUrl('app_question_show', [
                    'slug' => $question->getSlug(),
                ]);
            })
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-eye')
            ->setLabel('View on site')
        ;*/
        $viewAction = Action::new('view')
            ->linkToUrl(function (Question $question) {
                return $this->generateUrl('app_question_show', [
                    'slug' => $question->getSlug(),
                ]);
            })
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-eye')
            ->setLabel('View on site');

        $approveAction = Action::new('approve')
            ->setTemplatePath('admin/approve_action.html.twig')
            ->linkToCrudAction('approve')
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-check-circle')
            ->displayAsButton()
            ->displayIf(static function (Question $question): bool {
                return !$question->getIsApproved();
            });

        $exportAction = Action::new('export')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('export')
                    ->generateUrl();
            })
            ->addCssClass('btn btn-success')
            ->setIcon('fa fa-download')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            // show the "delete" action only for not approved questions
            ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action) {
                $action->displayIf(static function (Question $question) {
                    // always display, so we can try via the subscriber instead
                    return true;
                    //return !$question->getIsApproved();
                });
                return $action;
            })


            ->setPermission(Action::INDEX, 'ROLE_MODERATOR')
            ->setPermission(Action::DETAIL, 'ROLE_MODERATOR')
            ->setPermission(Action::EDIT, 'ROLE_MODERATOR')
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')

            ->add(Crud::PAGE_INDEX, $viewAction)
            ->add(Crud::PAGE_INDEX, $exportAction)
            //->add(Crud::PAGE_INDEX, $approveAction)
            ->add(Crud::PAGE_DETAIL, $viewAction)
            ->add(Crud::PAGE_DETAIL, $approveAction)

            ->reorder(Crud::PAGE_DETAIL, [
                'approve',
                'view',
                Action::EDIT,
                Action::INDEX,
                Action::DELETE,
            ])
        ;




    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('topic')
            ->add('createdAt')
            ->add('votes')
            ->add('name');
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Currently logged in user is not an instance of User?');
        }

        $entityInstance->setUpdatedBy($user);
        if (!$entityInstance instanceof Question) {
            throw new \LogicException('Currently Question object is not an instance of Question?');
        }

        parent::updateEntity($entityManager, $entityInstance);
    }


    /**
     * @param Question $entityInstance
     * @throws \Exception
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getIsApproved()) {
            throw new \Exception('Deleting approved questions is forbidden!');
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }


    public function approve(
        AdminContext $adminContext,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrlGenerator
    ): RedirectResponse {
        // get the current entity
        $question = $adminContext->getEntity()->getInstance();
        if (!$question instanceof Question) {
            throw new \LogicException('Currently Question object is not an instance of Question?');
        }

        $question->setIsApproved(true);
        $entityManager->flush();

        $targetUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($question->getId())
            ->generateUrl();

        return $this->redirect($targetUrl);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function export(AdminContext $context, CsvExporter $csvExporter): Response
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container
            ->get(FilterFactory::class)
            ->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        $filename = 'questions_'.date('d-m-Y_H-i-s').'.csv';
        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, $filename);
    }


}
