<?php
namespace Drupal\lit_contribution\EventSubscriber;
use Drupal\lit_contribution\Service\LocalOperationGate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Response;
final class LocalResetSubscriber implements EventSubscriberInterface {
  private $gate;
  public static function getSubscribedEvents():array{return [KernelEvents::REQUEST=>['guard',1000]];}
  public function guard(RequestEvent $event):void {
    if(!$event->isMainRequest() || !\Drupal\Core\Site\Settings::get('lit_local_demo_enabled',FALSE) || getenv('IS_DDEV_PROJECT')!=='true')return;
    try{$this->gate=LocalOperationGate::acquire();}
    catch(\Throwable $e){$event->setResponse(new Response('Local maintenance in progress. Recovery may be required.',503,['Cache-Control'=>'no-store','Retry-After'=>'60']));}
  }
}
