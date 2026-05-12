<?php

declare(strict_types=1);

namespace Wayfinder\Foundation;

use Throwable;
use Wayfinder\Http\Request;
use Wayfinder\Http\Response;
use Wayfinder\Http\ValidationException;
use Wayfinder\Logging\Logger;
use Wayfinder\Logging\NullLogger;
use Wayfinder\Routing\Router;
use Wayfinder\Routing\UrlGenerator;

final class AppKernel
{
    public function __construct(
        private readonly Router $router,
        private readonly bool $debug = false,
        private readonly Logger $logger = new NullLogger(),
        private readonly ?UrlGenerator $url = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->url?->setRequest($request);

        try {
            return $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            return $this->handleException($throwable, $request);
        }
    }

    public function run(): void
    {
        $this->handle(Request::fromGlobals())->send();
    }

    private function handleException(Throwable $throwable, Request $request): Response
    {
        $this->logger->error($throwable->getMessage(), [
            'exception' => $throwable::class,
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        if ($throwable instanceof ValidationException) {
            $activeRequest = $throwable->request() ?? $request;

            if ($activeRequest->hasSession() && ! $activeRequest->expectsJson()) {
                $session = $activeRequest->session();
                $input = $activeRequest->request();
                unset($input['_token']);

                $session->flash('_errors', $throwable->errors());
                $session->flash('_old_input', $input);

                return Response::redirect($activeRequest->redirectTarget());
            }

            return Response::json([
                'message' => $throwable->getMessage(),
                'errors' => $throwable->errors(),
            ], 422);
        }

        if ($this->debug) {
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $throwable->getMessage(),
                    'exception' => $throwable::class,
                    'file' => $throwable->getFile(),
                    'line' => $throwable->getLine(),
                    'trace' => $throwable->getTraceAsString(),
                ], 500);
            }

            return Response::text(
                "Internal Server Error\n"
                . sprintf("Exception: %s\n", $throwable::class)
                . sprintf("Message: %s\n", $throwable->getMessage())
                . sprintf("File: %s:%d\n\n", $throwable->getFile(), $throwable->getLine())
                . $throwable->getTraceAsString() . "\n",
                500,
            );
        }

        if ($request->expectsJson()) {
            return Response::json([
                'message' => 'Internal Server Error',
            ], 500);
        }

        return Response::text('Internal Server Error', 500);
    }
}
