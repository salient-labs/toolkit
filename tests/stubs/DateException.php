<?php

if (\PHP_VERSION_ID < 80300) {
    if (class_exists('DateException', false)) {
        return;
    }

    class DateException extends Exception {}
}
