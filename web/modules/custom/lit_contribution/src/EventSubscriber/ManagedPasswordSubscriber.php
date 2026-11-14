<?php
namespace Drupal\lit_contribution\EventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\user\Entity\User;
final class ManagedPasswordSubscriber implements EventSubscriberInterface {
  public static function getSubscribedEvents(): array { return [KernelEvents::CONTROLLER=>'onController']; }
  public function onController(ControllerEvent $event): void {
    $request=$event->getRequest();
    if(in_array($request->attributes->get('_route'),['user.reset','user.reset.login','user.reset.form'],TRUE)){
      $u=User::load((int)$request->attributes->get('uid'));
      if($u && lit_contribution_managed_credentials($u))throw new AccessDeniedHttpException('LiT credentials are managed by the superadmin.');
    }
  }
}
