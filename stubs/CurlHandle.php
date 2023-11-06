<?php

if (PHP_VERSION_ID < 80000 && extension_loaded('curl')) {
    final class CurlHandle {}
}
