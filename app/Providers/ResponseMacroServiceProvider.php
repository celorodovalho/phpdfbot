<?php

namespace App\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

/**
 * Class ResponseMacroServiceProvider
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class ResponseMacroServiceProvider extends ServiceProvider
{
    private const SUCCESS = 'success';
    private const STATUS = 'status';
    private const MESSAGE = 'message';
    private const CODE = 'code';
    private const META = 'meta';
    private const DATA = 'data';
    private const ERRORS = 'errors';
    private const ERROR = 'error';
    private const FAILED = 'failed';
    private const CREATED = 'created';
    private const NO_CONTENT = 'noContent';
    private const UNAUTHORIZED = 'unauthorized';
    private const FORBIDDEN = 'forbidden';
    private const NOT_FOUND = 'notFound';
    private const UNAVAILABLE = 'unavailable';

    /**
     * Register the application's response macros.
     *
     * @return void
     */
    public function boot(): void
    {
        /** 200: OK. The standard success code and default option. */
        Response::macro(self::SUCCESS, array($this, 'macroSuccess'));

        /** 201: Object created. Useful for the store actions. */
        Response::macro(self::CREATED, array($this, 'macroCreated'));

        /** 204: No content. When an action was executed successfully, but there is no content to return. */
        Response::macro(self::NO_CONTENT, array($this, 'macroNoContent'));

        /** 400: Bad request. The standard option for requests that fail to pass validation. */
        Response::macro(self::FAILED, array($this, 'macroFailed'));

        /** 401: Unauthorized. The user needs to be authenticated. */
        Response::macro(self::UNAUTHORIZED, array($this, 'macroUnauthorized'));

        /** 403: Forbidden. The user is authenticated, but does not have the permissions to perform an action. */
        Response::macro(self::FORBIDDEN, array($this, 'macroForbidden'));

        /** 404: Not found. This will be returned automatically by Laravel when the resource is not found. */
        Response::macro(self::NOT_FOUND, array($this, 'macroNotFound'));

        /** 500: Internal server error. Ideally you're not going to be explicitly returning this,
         * but if something unexpected breaks, this is what your user is going to receive. */
        Response::macro(self::ERROR, array($this, 'macroError'));

        /** 503: Service unavailable. Pretty self explanatory, but also another code that is not
         * going to be returned explicitly by the application. */
        Response::macro(self::UNAVAILABLE, array($this, 'macroUnavailable'));
    }

    /**
     * @param string $message
     * @param null   $value
     *
     * @return JsonResponse
     */
    public function macroSuccess($message = 'Success!', $value = null): JsonResponse
    {
        return Response::json([
            self::DATA => is_array($value) && array_key_exists(self::DATA, $value) ? $value[self::DATA] : $value,
            self::META => [
                self::STATUS => self::SUCCESS,
                self::CODE => HttpResponse::HTTP_OK,
                self::MESSAGE => $message,
            ],
        ], HttpResponse::HTTP_OK);
    }

    /**
     * @param string $message
     * @param null   $value
     *
     * @return JsonResponse
     */
    public function macroCreated(string $message = 'Resource created!', $value = null): JsonResponse
    {
        return Response::json([
            self::DATA => array_key_exists(self::DATA, $value) ? $value[self::DATA] : $value,
            self::META => [
                self::STATUS => self::SUCCESS,
                self::CODE => HttpResponse::HTTP_CREATED,
                self::MESSAGE => $message,
            ],
        ], HttpResponse::HTTP_CREATED);
    }

    /**
     * @return JsonResponse
     */
    public function macroNoContent(): JsonResponse
    {
        return Response::json(null, HttpResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param string $value
     * @param array  $errors
     *
     * @return JsonResponse
     */
    public function macroFailed(string $value = 'Request is failed!', $errors = []): JsonResponse
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => HttpResponse::HTTP_BAD_REQUEST,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], HttpResponse::HTTP_BAD_REQUEST);
    }

    /**
     * @param string $value
     * @param array  $errors
     *
     * @return JsonResponse
     */
    public function macroUnauthorized(string $value = 'Authentication is required!', $errors = []): JsonResponse
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => HttpResponse::HTTP_UNAUTHORIZED,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], HttpResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * @param string $value
     * @param array  $errors
     *
     * @return JsonResponse
     */
    public function macroForbidden(string $value = 'Permission is required!', $errors = []): JsonResponse
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => HttpResponse::HTTP_FORBIDDEN,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], HttpResponse::HTTP_FORBIDDEN);
    }

    /**
     * @param string $value
     * @param array  $errors
     *
     * @return JsonResponse
     */
    public function macroNotFound(string $value = 'Resource not found!', $errors = []): JsonResponse
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => HttpResponse::HTTP_NOT_FOUND,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * @param string $value
     * @param array  $errors
     *
     * @return JsonResponse
     */
    public function macroError(string $value = 'An error occurred.', array $errors = []): JsonResponse
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::ERROR,
                self::CODE => HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param string $value
     * @param array  $errors
     *
     * @return JsonResponse
     */
    public function macroUnavailable(string $value = 'Service unavailable.', array $errors = []): JsonResponse
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::ERROR,
                self::CODE => HttpResponse::HTTP_SERVICE_UNAVAILABLE,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
