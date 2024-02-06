<?php

if (\PHP_VERSION_ID < 80300) {
    if (class_exists('DateMalformedIntervalStringException', false)) {
        return;
    }

    class DateMalformedIntervalStringException extends DateException {}
}
