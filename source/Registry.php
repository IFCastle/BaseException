<?PHP
namespace Exceptions;

/**
 * Register of exceptions.
 *
 * This is a static class which used as global registry for exceptions.
 * It defines the internal storage for exceptions which can be redefined by a programmer.
 *
 * Really this class not log an exception.
 * It's stores them until called $save_handler.
 */
class Registry
{
    /**
     * Options for logger
     * @var array|\ArrayAccess
     */
    static public $Logger_options = [];

    /**
     * Options for debug mode
     * @var array|\ArrayAccess
     */
    static public $Debug_options  = [];

    /**
     * List of exception
     *
     * @var BaseException[]|\Exception[]|StorageI
     */
    static protected $exceptions = [];

    /**
     * Handler which called from save_exception_log
     *
     * @var SaveHandlerI
     */
    static protected $save_handler;

    /**
     * Handler for unhandled exception
     * @var HandlerI
     */
    static protected $unhandled_handler;

    /**
     * Handler called for fatal exception
     * @var HandlerI
     */
    static protected $fatal_handler;

    /**
     * Old error handler
     * @var callback
     */
    static protected $old_error_handler;

    /**
     * Old exception handler
     * @var callback
     */
    static protected $old_exception_handler;

    /**
     * Setup global handler flag
     * @var boolean
     */
    static protected $install_global_handlers;

    /**
     * List of fatal php error
     * @var array
     */
    protected static $FATAL = array
    (
        E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR
    );

    final private function __construct(){}

    /**
     * Registered exception.
     *
     * This method may be used with set_exception_handler()
     *
     * @param BaseExceptionI|\Throwable     $exception
     *
     */
    static public function register_exception($exception)
    {
        if(($exception instanceof BaseExceptionI)   === false
        && ($exception instanceof \Throwable)       === false )
        {
            return;
        }

        if(is_array(self::$exceptions))
        {
            self::$exceptions[] = $exception;
        }
        elseif(self::$exceptions instanceof StorageI)
        {
            self::$exceptions->add_exception($exception);
        }
    }

    /**
     * Returns the list of exception
     *
     * @return      BaseException[]|\Exception[]
     */
    static public function get_exception_log()
    {
        if(is_array(self::$exceptions))
        {
            return self::$exceptions;
        }
        elseif(self::$exceptions instanceof StorageI)
        {
            $result = self::$exceptions->get_storage();
            if(!is_array($result))
            {
                return array(new \UnexpectedValueException('StorageI->get_storage() return not array'));
            }
            else
            {
                return $result;
            }
        }
        else
        {
            return array();
        }
    }

    /**
     * Resets exception storage
     */
    static public function reset_exception_log()
    {
        if(self::$exceptions instanceof StorageI)
        {
            self::$exceptions->reset_storage();
        }
        else
        {
            self::$exceptions = [];
        }
    }

    /**
     * Saves registry exceptions to log.
     */
    static public function save_exception_log()
    {
        if(self::$save_handler instanceof SaveHandlerI)
        {
            self::$save_handler->save_exceptions
            (
                (self::$exceptions instanceof StorageI) ? self::$exceptions->get_storage() : self::$exceptions,
                [__CLASS__, 'reset_exception_log'],
                self::$Logger_options,
                self::$Debug_options
            );
        }
    }

    /**
     * Setup custom storage for exceptions
     *
     * @param       StorageI     $storage      Custom storage
     *
     * @return      StorageI                   returns older storage if exists
     */
    static public function set_registry_storage(StorageI $storage)
    {
        $old = self::$exceptions;

        self::$exceptions = $storage;

        return $old;
    }

    /**
     * Setup save handler
     *
     * @param       SaveHandlerI                $handler       Handler
     *
     * @return      SaveHandlerI|null           Returns old handler if exists
     */
    static public function set_save_handler(SaveHandlerI $handler = null)
    {
        $old = self::$save_handler;

        self::$save_handler = $handler;

        return $old;
    }

    /**
     * @param       HandlerI        $handler
     *
     * @return      HandlerI|null
     */
    static public function set_unhandled_handler(HandlerI $handler = null)
    {
        $old = self::$unhandled_handler;

        self::$unhandled_handler = $handler;

        return $old;
    }

    /**
     * @param       HandlerI        $handler
     *
     * @return      HandlerI|null
     */
    static public function set_fatal_handler(HandlerI $handler = null)
    {
        $old = self::$fatal_handler;

        self::$fatal_handler = $handler;

        return $old;
    }

    /**
     * Invokes the handler if there is
     *
     * @param       BaseExceptionI      $exception
     */
    static public function call_fatal_handler(BaseExceptionI $exception = null)
    {
        if(self::$fatal_handler instanceof HandlerI)
        {
            self::$fatal_handler->exception_handler($exception);
        }
    }

    /**
     * Return list of logger options
     *
     * @return      array
     */
    static public function get_logger_options()
    {
        if(is_array(self::$Logger_options) ||
        self::$Logger_options instanceof \ArrayAccess)
        {
            return self::$Logger_options;
        }
        else
        {
            return [];
        }
    }

    /**
     * Registers three default handlers:
     *
     * 1.  shutdown_function
     * 2.  error_handler
     * 3.  exception_handler
     *
     */
    static public function install_global_handlers()
    {
        if(self::$install_global_handlers)
        {
            return;
        }

        register_shutdown_function([__CLASS__, 'shutdown_function']);
        self::$old_error_handler        = set_error_handler([__CLASS__, 'error_handler']);
        self::$old_exception_handler    = set_exception_handler([__CLASS__, 'exception_handler']);
        self::$install_global_handlers  = true;
    }

    /**
     * Restores default handlers.
     *
     */
    static public function restore_global_handlers()
    {
        if(!self::$install_global_handlers)
        {
            return;
        }

        self::$install_global_handlers  = false;

        if(!empty(self::$old_error_handler))
        {
            set_error_handler(self::$old_error_handler);
        }
        else
        {
            restore_error_handler();
        }

        if(!empty(self::$old_exception_handler))
        {
            set_exception_handler(self::$old_exception_handler);
        }
        else
        {
            restore_exception_handler();
        }
    }


    static public function exception_handler(\Throwable $exception)
    {
        if($exception instanceof BaseExceptionI)
        {
            // When exception reaches this handler
            // its not logged if:
            // - already was logged
            // - or is container
            if($exception->is_loggable() || $exception->is_container())
            {
                new UnhandledException($exception);
                return;
            }

            $exception->set_loggable(true);
        }

        self::register_exception($exception);

        new UnhandledException($exception);

        if(self::$unhandled_handler instanceof HandlerI)
        {
            self::$unhandled_handler->exception_handler($exception);
        }
    }

    /**
     * The method for set_error_handler
     *
     * @param        int            $errno      Class of error
     * @param        string         $errstr     Description
     * @param        string         $errfile    File
     * @param        string         $errline    Line
     *
     * @return       boolean
    */
    static public function error_handler($errno, $errstr, $errfile, $errline)
    {
        self::register_exception
        (
            Errors\Error::create_error($errno, $errstr, $errfile, $errline)
        );

        /* Don't execute PHP internal error handler */
        return true;
    }

    static public function fatal_error_handler()
    {
        $error = error_get_last();
        if (!is_array($error) || !in_array($error['type'], self::$FATAL))
        {
            return;
        }
        self::error_handler($error['type'], $error['message'], $error['file'], $error['line']);
    }

    static public function shutdown_function()
    {
        self::fatal_error_handler();
        self::save_exception_log();
    }

    /**
     * Returns true if debug mode was enabled
     *
     * @param       string      $class          name of class or namespace
     *
     * @return      boolean
     */
    static public function is_debug($class = null)
    {
        // If global debug mode on - return true.
        if(isset(self::$Debug_options['debug']) && self::$Debug_options['debug'])
        {
            return true;
        }

        // if namespaces not defined - return
        if(is_null($class) || empty(self::$Debug_options['namespaces']))
        {
            return false;
        }

        // Searching for matches
        foreach(self::$Debug_options['namespaces'] as $namespace)
        {
            if(strpos($class, $namespace) === 0)
            {
                return true;
            }
        }

        return false;
    }
}