<?php

if (\PHP_VERSION_ID < 80300 && !class_exists('DateException', false)) {
    class DateException extends Exception {}
}
