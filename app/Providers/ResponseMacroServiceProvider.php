<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

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

    /**
     * Register the application's response macros.
     *
     * @return void
     */
    public function boot()
    {
        /** 200: OK. The standard success code and default option. */
        Response::macro(self::SUCCESS, array($this, 'macroSuccess'));
        /** 201: Object created. Useful for the store actions. */
        Response::macro('created', array($this, 'macroCreated'));
        /** 204: No content. When an action was executed successfully, but there is no content to return. */
        Response::macro('noContent', array($this, 'macroNoContent'));
        /** 400: Bad request. The standard option for requests that fail to pass validation. */
        Response::macro(self::FAILED, array($this, 'macroFailed'));
        /** 401: Unauthorized. The user needs to be authenticated. */
        Response::macro('unauthorized', array($this, 'macroUnauthorized'));
        /** 403: Forbidden. The user is authenticated, but does not have the permissions to perform an action. */
        Response::macro('forbidden', array($this, 'macroForbidden'));
        /** 404: Not found. This will be returned automatically by Laravel when the resource is not found. */
        Response::macro('notFound', array($this, 'macroNotFound'));
        /** 500: Internal server error. Ideally you're not going to be explicitly returning this,
         * but if something unexpected breaks, this is what your user is going to receive. */
        Response::macro(self::ERROR, array($this, 'macroError'));
        /** 503: Service unavailable. Pretty self explanatory, but also another code that is not
         * going to be returned explicitly by the application. */
        Response::macro('unavailable', array($this, 'macroUnavailable'));
    }

    public function macroSuccess($message = 'Success!', $value = null)
    {
        return Response::json([
            self::DATA => is_array($value) && array_key_exists(self::DATA, $value) ? $value[self::DATA] : $value,
            self::META => [
                self::STATUS => self::SUCCESS,
                self::CODE => \Illuminate\Http\Response::HTTP_OK,
                self::MESSAGE => $message,
            ],
        ], \Illuminate\Http\Response::HTTP_OK);
    }

    public function macroCreated(string $message = 'Resource created!', $value = null)
    {
        return Response::json([
            self::DATA => array_key_exists(self::DATA, $value) ? $value[self::DATA] : $value,
            self::META => [
                self::STATUS => self::SUCCESS,
                self::CODE => \Illuminate\Http\Response::HTTP_CREATED,
                self::MESSAGE => $message,
            ],
        ], \Illuminate\Http\Response::HTTP_CREATED);
    }

    public function macroNoContent()
    {
        return Response::json(null, \Illuminate\Http\Response::HTTP_NO_CONTENT);
    }

    public function macroFailed(string $value = 'Request is failed!', $errors = [])
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => \Illuminate\Http\Response::HTTP_BAD_REQUEST,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], \Illuminate\Http\Response::HTTP_BAD_REQUEST);
    }

    public function macroUnauthorized(string $value = 'Authentication is required!', $errors = [])
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => \Illuminate\Http\Response::HTTP_UNAUTHORIZED,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], \Illuminate\Http\Response::HTTP_UNAUTHORIZED);
    }

    public function macroForbidden(string $value = 'Permission is required!', $errors = [])
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => \Illuminate\Http\Response::HTTP_FORBIDDEN,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], \Illuminate\Http\Response::HTTP_FORBIDDEN);
    }

    public function macroNotFound(string $value = 'Resource not found!', $errors = [])
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::FAILED,
                self::CODE => \Illuminate\Http\Response::HTTP_NOT_FOUND,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], \Illuminate\Http\Response::HTTP_NOT_FOUND);
    }

    public function macroError(string $value = 'An error occurred.', array $errors = [])
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::ERROR,
                self::CODE => \Illuminate\Http\Response::HTTP_INTERNAL_SERVER_ERROR,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], \Illuminate\Http\Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function macroUnavailable(string $value = 'Service unavailable.', array $errors = [])
    {
        return Response::json([
            self::DATA => null,
            self::META => [
                self::STATUS => self::ERROR,
                self::CODE => \Illuminate\Http\Response::HTTP_SERVICE_UNAVAILABLE,
                self::MESSAGE => $value,
            ],
            self::ERRORS => $errors,
        ], \Illuminate\Http\Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
