<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener]
class LogoutEventListener
{
    public function __construct(
        private readonly string $frontendUrl
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        // Принудительно задаем ответ редиректа на адрес Vue-фронтенда
        $event->setResponse(new RedirectResponse($this->frontendUrl));
    }
}
