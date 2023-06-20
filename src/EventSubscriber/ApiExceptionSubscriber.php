<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        // Get exception from current event
        $exception = $event->getThrowable();

        // Default status code is always 500
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;

        // HttpExceptionInterface is a special type of exception that holds status code and header details
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        $data = [
            'code' => $statusCode,
            'message' => $exception->getMessage(),
        ];

        // Send the modified response object to the event
        $event->setResponse(new JsonResponse($data, $statusCode));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }
}
