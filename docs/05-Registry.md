# Registry

Класс `Registry` является статическим. Хотя в `PHP` пока нет статических классов по типу C#, в `Registry` вызов конструктора запрещён.

`Registry` обеспечивает регистрацию исключений до того, как они будут помещены в журнал. Для этого `Registry` использует Хранилище, которое может быть переопределено программистом:

```php
    static public function set_registry_storage(StorageI $storage)
```

Интерфейс `StorageI` содержит всего несколько методов, и позволяет влиять на ход регистрации исключений.

Дополнительное расширение функциональности `Registry` можно получить, используя методы:

- `set_unhandled_handler` - необработанные исключения.
- `set_fatal_handler`     - ошибка с флагом `is_fatal`.

Метод `set_unhandled_handler` используется тогда, когда `Registry` позволено обрабатывать потоки ошибок и исключений `PHP`. Для этого нужно вызвать метод `Registry::install_global_handlers()`, после чего в реестр начнут попадать все необработанные исключения и ошибки.

Обработчик `set_unhandled_handler` будет вызван после основного обработчика `Registry::exception_handler(\Exception $exception)`.

Метод `set_fatal_handler` будет рассмотрен подробнее в "Fatal Exception".

## Журналирование ошибок PHP

`Registry` поддерживает журналирование не только исключений, но и потока ошибок `PHP`. Для этого он использует класс `Exceptions\Errors\Error`, который хотя и не является исключением, но реализует интерфейс `BaseExceptionI`.

Все ошибки кроме `E_USER_*`, считаются ошибками программиста, и попадают в общий журнал. Это же касается `E_NOTICE` и `E_DEPRECATED`. 

Таким образом `Registry` требует, чтобы код `PHP` был полностью чист от предупреждений любого рода.

## Необработанные исключения

Что произойдёт с исключением, если оно не было обработано и попало в `Registry::exception_handler()`? 

```php
        if($exception instanceof BaseExceptionI)
        {
            // Если исключение достигает обработчика
            // оно не логируется в случае, если:
            // - уже было журналированным
            // - или если является контейнером для другого исключения
            if($exception->is_loggable() || $exception->is_container())
            {
                new UnhandledException($exception);
                return;
            }

            $exception->set_loggable(true);
        }

        self::register_exception($exception);

        new UnhandledException($exception);
        ...
```

`Registry` придерживается алгоритма:

1. Если исключение `BaseException` имеет сброшенный флаг `is_loggable` оно всё равно будет журналировано как необработанное.
2. Если исключение уже находится в журнале - нет смысла его помещать туда снова.
3. Если исключение является контейнером, то ответственность за журналирование лежит на нём.

Обратите внимание на `new UnhandledException($exception)`. Это исключение используется только как маркер факта, что последнее исключение не было поймано:

```php
class UnhandledException extends LoggableException
{
    public function __construct(\Exception $exception)
    {
        parent::__construct
        ([
            'message'   => 'Unhandled Exception',
            'type'      => get_class($exception),
            'source'    => self::get_source_for($exception),
            'previous'  => $exception
        ]);
    }
}
```

Таким образом в журнале сохранится отдельная запись об необработанном исключении, и можно будет отличить два случая: когда целевое исключение было обработано, а когда - нет.

## Двойное журналирование

Нужно отметить, что возможен особый случай двойного журналирования:

1. Исключение имеет установленный флаг `is_loggable` и попадает в журнал.
2. Другой код ловит исключение, и сбрасывает флаг
3. Другой код снова выбрасывает это же исключение
4. Исключение становится наобработанным.
5. Так как его флаг `is_loggable` равен `false`, оно логируется заново.

Я решил оставить данный Use Case как есть, и допустить двойную запись исключения, так как, если в коде происходит что-то подобное, значит его нужно срочно пересмотреть.
