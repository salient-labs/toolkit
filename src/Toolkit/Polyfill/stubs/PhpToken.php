<?php

if (\PHP_VERSION_ID < 80000 && extension_loaded('tokenizer')) {
    if (class_exists('PhpToken', false)) {
        return;
    }

    class PhpToken extends Salient\Polyfill\PhpToken {}
}
