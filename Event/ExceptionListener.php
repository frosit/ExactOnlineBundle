<?php

namespace aibianchi\ExactOnlineBundle\Event;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use aibianchi\ExactOnlineBundle\DAO\Exception\ApiExceptionInterface;

class ExceptionListener{

   private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($event->getException() instanceof ApiExceptionInterface || $event->getException() instanceof GuzzleException) {

            $response = new JsonResponse($event->getException()->getMessage());
            $event->setResponse($response);

            $this->log($event->getException());
        }

    }

    private function log($exception)
    {
        $log = [
            'code' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : $exception->getCode(),
            'message' => $exception->getMessage(),
            'called' => [
                'file' => $exception->getTrace()[0]['file'],
                'line' => $exception->getTrace()[0]['line'],
            ],
            'occurred' => [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
        ];

        if ($exception->getPrevious() instanceof Exception) {
            $log += [
                'previous' => [
                    'message' => $exception->getPrevious()->getMessage(),
                    'exception' => get_class($exception->getPrevious()),
                    'file' => $exception->getPrevious()->getFile(),
                    'line' => $exception->getPrevious()->getLine(),
                ],
            ];
        }

        $this->logger->error(json_encode($log, JSON_UNESCAPED_SLASHES));
    }

}


?>