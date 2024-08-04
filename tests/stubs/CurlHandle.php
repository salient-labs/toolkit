<?php

if (\PHP_VERSION_ID < 80000 && extension_loaded('curl') && !class_exists('CurlHandle', false)) {
    final class CurlHandle {}
}
