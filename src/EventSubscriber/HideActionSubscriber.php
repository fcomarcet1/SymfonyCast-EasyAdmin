<?php

namespace App\EventSubscriber;

use App\Entity\Question;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;

class HideActionSubscriber implements EventSubscriberInterface
{
    public function onBeforeCrudActionEvent(BeforeCrudActionEvent $event)
    {
        if (!$adminContext = $event->getAdminContext()) {
            return;
        }
        if (!$crudDto = $adminContext->getCrud()) {
            return;
        }
        if ($crudDto->getEntityFqcn() !== Question::class) {
            return;
        }

        // disable action entirely delete, detail, edit
        $question = $adminContext->getEntity()->getInstance();
        if ($question instanceof Question && $question->getIsApproved()) {
            $crudDto->getActionsConfig()->disableActions([Action::DELETE]);
        }

        // This gives you the "configuration for all the actions".
        // Calling ->getActions() returns the array of actual actions that will be
        // enabled for the current page... so then we can modify the one for "delete"
        $actions = $crudDto->getActionsConfig()->getActions();
        if (!$deleteAction = $actions[Action::DELETE] ?? null) {
            return;
        }
        $deleteAction->setDisplayCallable(function(Question $question) {
            return !$question->getIsApproved();
        });
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCrudActionEvent::class => 'onBeforeCrudActionEvent',
        ];
    }
}
