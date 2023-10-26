<?php

if (PHP_VERSION_ID < 80000 && extension_loaded('tokenizer')) {
    class PhpToken extends Lkrms\Polyfill\PhpToken {}
}
