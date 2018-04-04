<?php

namespace Hail\Http\Middleware;

use Hail\Application;
use Hail\Exception\ActionForward;
use Hail\Exception\BadRequestException;
use Hail\Http\Exception\HttpErrorException;
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface
};

class Handler implements MiddlewareInterface
{
    /**
     * @var Application
     */
    protected $app;

    protected $reties = 0;
    protected $max;

    /**
     * @param Application $app
     * @param int $max
     */
    public function __construct(Application $app, $max = null)
    {
        $this->app = $app;
        $this->max = $max ?: 10;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface      $handler
     *
     * @return ResponseInterface
     * @throws HttpErrorException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * @param ServerRequestInterface|array $handler
     *
     * @return ResponseInterface
     * @throws HttpErrorException
     */
    protected function handle($handler)
    {
        ++$this->reties;
        if ($this->reties > $this->max) {
            throw HttpErrorException::create(500, [
                'code' => 500,
                'message' => 'Action forward is too much',
            ]);
        }

        try {
            if ($handler instanceof ServerRequestInterface) {
                $handler = $this->app->dispatch($handler);
            }

            return $this->app->handle($handler);
        } catch (ActionForward $e) {
            $this->app->params(
                $e->getParams()
            );

            return $this->handle(
                $this->app->handler(
                    $e->getHandler()
                )
            );
        } catch (BadRequestException $e) {
            throw HttpErrorException::create($e->getCode(), [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], $e);
        }
    }
}