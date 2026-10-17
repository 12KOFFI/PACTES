<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\SessionContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Active le filtre de session pour le personnel (admin, établissement, jury, agent, com).
 * Les candidats et les visiteurs anonymes (vérification de statut, convocations publiques) ne sont pas filtrés.
 * Le filtre ne remplace aucun contrôle de rôle ou d'établissement : il s'y ajoute.
 */
class SessionContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SessionContext $sessionContext,
        private EntityManagerInterface $em,
        private Security $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $staffRoles = array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_CANDIDAT']);
        if (!$staffRoles) {
            return;
        }

        $session = $this->sessionContext->resolve($event->getRequest());
        if (!$session) {
            return;
        }

        $filter = $this->em->getFilters()->enable('session_filter');
        $filter->setParameter('session_id', $session->getId(), 'integer');
    }
}