<?php

if (\PHP_VERSION_ID < 80300 && !class_exists('DateMalformedIntervalStringException', false)) {
    class DateMalformedIntervalStringException extends DateException {}
}
