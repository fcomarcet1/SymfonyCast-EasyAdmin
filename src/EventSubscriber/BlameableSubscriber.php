<?php

namespace App\EventSubscriber;

use App\Entity\Question;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\Security\Core\Security;

class BlameableSubscriber implements EventSubscriberInterface
{
    public function __construct(private Security $security)
    {
    }
    public function onBeforeEntityUpdatedEvent(BeforeEntityUpdatedEvent $event)
    {
        // Get entity question from event
        $question = $event->getEntityInstance();
        if (!$question instanceof Question){
            return;
        }

        // Get user
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Currently logged in user is not an instance of User?!');
        }

        $question->setUpdatedBy($user);
    }


    public static function getSubscribedEvents()
    {
        return [
            BeforeEntityUpdatedEvent::class => 'onBeforeEntityUpdatedEvent',
        ];
    }
}
