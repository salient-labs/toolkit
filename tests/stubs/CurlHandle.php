<?php

if (\PHP_VERSION_ID < 80000 && extension_loaded('curl')) {
    if (class_exists('CurlHandle', false)) {
        return;
    }

    final class CurlHandle {}
}
