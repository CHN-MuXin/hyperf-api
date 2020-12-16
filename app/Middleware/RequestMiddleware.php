<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\StatusCode;
use App\Exception\Handler\BusinessException;
use App\Foundation\Facades\Log;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Phper666\JWTAuth\JWT;
use Phper666\JWTAuth\Util\JWTUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    /**
     * @var JWT
     */
    protected $jwt;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request, JWT $jwt)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
        $this->jwt = $jwt;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requireParams = $request->getQueryParams();
        //记录请求参数日志记录

        if (config('request_log')) Log::requestLog()->info('请求参数：' . json_encode($requireParams));
        $isValidToken = false;
        // 根据具体业务判断逻辑走向，这里假设用户携带的token有效
        $token = $request->getHeaderLine('Authorization') ?? '';
        if (strlen($token) > 0) {
            $token = JWTUtil::handleToken($token);
            if ($token !== false && $this->jwt->checkToken($token)) {
                $isValidToken = true;
            }
        }
        if ($isValidToken) return $handler->handle($request);

        Throw new BusinessException(StatusCode::ERR_INVALID_TOKEN, 'Token无效或者过期');
    }
}
