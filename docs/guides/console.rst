Terminal output and logging
===========================

With a similar API to the ``console`` object provided by web browsers, the
`Console`_ class [1]_ provides:

-  Familiar methods like ``Console::log()`` and ``Console::error()``
-  Variants like ``Console::logOnce()`` and ``Console::errorOnce()`` to output
   messages once per run
-  Output to multiple targets
-  Messages filtered by log level
-  Formatting to reflect message priority and improve readability
-  Colour output to TTYs

Default targets
---------------

By default, all `Console`_ messages are written to a file created with mode 0600
at:

.. code:: php

   sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'

If PHP is running on the command line, errors and warnings are also written to
``STDERR``, informational messages are written to ``STDOUT``, and if environment
variable ``DEBUG`` is non-empty, debug messages are also written to ``STDOUT``.

To override these defaults, register at least one `Console`_ output target by
calling `registerStdioTargets()`_ or `registerTarget()`_ before any other
``Console`` methods can be called, preferably while bootstrapping your
application.

   `AppContainer`_ and `CliAppContainer`_ always call `registerStdioTargets()`_.
   This registers the default ``STDOUT`` and ``STDERR`` targets explicitly and
   prevents creation of the temporary default output log. To create a log file
   that persists between reboots (in your project’s ``var/log`` directory by
   default), call the app container’s `logConsoleMessages()`_ method.

Output methods
--------------

+--------------------+---------------------+----------------+-------------------------------+
| ``Console`` method | ``ConsoleLevel``    | Message prefix | Default output target         |
+====================+=====================+================+===============================+
| ``error[Once]()``  | ``ERROR`` = ``3``   | ``!!``         | ``STDERR``                    |
+--------------------+---------------------+----------------+-------------------------------+
| ``warn[Once]()``   | ``WARNING`` = ``4`` | ``!``          | ``STDERR``                    |
+--------------------+---------------------+----------------+-------------------------------+
| ``info[Once]()``   | ``NOTICE`` = ``5``  | ``==>``        | ``STDOUT``                    |
+--------------------+---------------------+----------------+-------------------------------+
| ``log[Once]()``    | ``INFO`` = ``6``    | ``->``         | ``STDOUT``                    |
+--------------------+---------------------+----------------+-------------------------------+
| ``debug[Once]()``  | ``DEBUG`` = ``7``   | ``---``        | ``STDOUT`` (if ``DEBUG`` set) |
+--------------------+---------------------+----------------+-------------------------------+
| ``group()``\  [2]_ | ``NOTICE`` = ``5``  | ``>>>``        | ``STDOUT``                    |
+--------------------+---------------------+----------------+-------------------------------+
| ``logProgress()``  | ``INFO`` = ``6``    | ``->``         | ``STDOUT``                    |
+--------------------+---------------------+----------------+-------------------------------+


.. [1]
   Actually a facade for `ConsoleWriter`_.

.. [2]
   ``Console::group()`` adds a level of indentation to all ``Console`` output
   until ``Console::groupEnd()`` is called.

.. _Console: https://lkrms.github.io/php-util/classes/Lkrms-Facade-Console.html
.. _registerStdioTargets(): https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerStdioTargets
.. _registerTarget(): https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerTarget
.. _AppContainer: https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html
.. _CliAppContainer: https://lkrms.github.io/php-util/classes/Lkrms-Container-CliAppContainer.html
.. _logConsoleMessages(): https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html#method_logConsoleMessages
.. _ConsoleWriter: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html
