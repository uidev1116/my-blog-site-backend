<?php

namespace Acms\Services\Facades;

/**
 * Class Application
 *
 * @method static bool isAcmsJsLoaded() Check if ACMS JS is loaded
 * @method static void setIsAcmsJsLoaded(bool $isAcmsJsLoaded) Set the ACMS JS loaded status
 * @method static \Exception|null getExceptionStack() Get the exception stack
 * @method static void setExceptionStack(?\Exception $exceptionStack) Set the exception stack
 * @method static void checkException() Check for exceptions and throw if any
 * @method static void deleteExceptionStack() Delete the exception stack
 * @method static void exception(string $message, int $code = 0) Stack an exception
 * @method static void showError(\Exception $e, bool $log = true) Show error details
 * @method static mixed licenseActivation(string $licenseFilePath) Activate license
 * @method static void loadLicense() Load license
 * @method static \Field getQueryParameter() Get query parameters
 * @method static \Field getGetParameter() Get GET parameters
 * @method static \Field_Validation getPostParameter() Get POST parameters
 * @method static \Field getCookieParameter() Get cookie parameters
 * @method static bool exists(string $alias) Check if a service is registered in the DI container
 * @method static array aliasList() Get the list of registered services in the DI container
 * @method static mixed make(string $alias) Get a service from the DI container
 * @method static void bind(string $alias, string|callable $class, array $arguments = []) Register a service in the DI container
 * @method static void singleton(string $alias, string|callable $class, array $arguments = []) Register a service as a singleton in the DI container
 * @method static void forgetInstance(string $alias) Drop the cached singleton instance so the next make() rebuilds it (test only)
 * @method static object instance(string $alias, object $instance) Replace a service with a concrete object (test helper, equivalent to Laravel's Container::instance())
 * @method static void bootstrap(string $alias, callable $callback) Register a bootstrap function for a service
 * @method static void init(array $aliases = [], array $providers = [], bool $ignore_error = false) Initialize the application
 */
class Application extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'application';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
