<?php
namespace Drupal\lit_contribution\EventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
final class PrivateResponseSubscriber implements EventSubscriberInterface {
  public static function getSubscribedEvents(): array { return [KernelEvents::RESPONSE=>['onResponse',-1000]]; }
  public function onResponse(ResponseEvent $event): void {
    $route=(string)$event->getRequest()->attributes->get('_route');
    if (str_starts_with($route,'lit_contribution.') && $route!=='lit_contribution.map') {
      $headers=$event->getResponse()->headers;
      $headers->set('Cache-Control','private, no-store');
      $headers->set('Referrer-Policy','no-referrer');
      $headers->set('X-Robots-Tag','noindex, nofollow');
    }
  }
}
