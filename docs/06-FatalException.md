# Fatal Exception

Аспект "Фатальная ошибка" реализован в виде свойства `is_fatal`, а так же публичного метода: `is_fatal()`. Интересно заметить, что по сравнению с флагом `is_loggable()` у флага `is_fatal` нет возможности сброса (разве что изнутри класса).

Так как `is_fatal` - это свойство, то фатальным может стать любое исключение в любой момент времени:

```php
    try
    {
        ...
    }
    catch(BaseException $e)
    {
        throw $e->set_fatal();
    }
```

Можно так же использовать исключение-контейнер, чтобы наделить этой характеристикой другое исключение:

```php
    try
    {
        ...
    }
    catch(BaseException $e)
    {
        // Теперь исключение $e - имеет аспект фатального.
        throw new FatalException($e);
    }
```

## Обработчик фатальных исключений

`Registry` предоставляет метод `Registry::set_fatal_handler($callback)` для обработки появления фатальных исключений.

Прототип обработчика:

```php
    function(BaseExceptionI $exception)
    {
        // Если это контейнер - используем его содержимое
        if($exception->is_container())
        {
            $real_exception = $exception->get_previous();
        }
        else
        {
            $real_exception = $exception;
        }
        ...
    }
```

Обработчик фатальных исключений вызывается в момент, когда исключение становится фатальным, то есть:

1. В конце конструктора исключения `BaseException`.
2. В момент вызова метода `set_fatal()`.

Обработчик фатального исключения вызывается не для журналирования. Он нужен для введения особого специального алгоритма, в условиях возможной нехватки ресурсов. Возможные задачи обработчика таковы:

- остановить программу или сервис;
- предотвратить повторные запуски work-еров, пока проблема не решиться;
- запустить процесс анализа сбоя.

## Особенности обработки для журнализатора

Журнализатор так же может обрабатывать фатальное исключение не так, как обычно:

1. Проверить возможность записи на диск или в файл журнала.
2. Если файл журнала не доступен, попытаться использовать системный журнал.
3. Использовать EMAIL для отправки уведомления.
4. Если EMAIL не работает, использовать альтернативные каналы.