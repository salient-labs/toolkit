<?php

if (\PHP_VERSION_ID < 80000 && extension_loaded('tokenizer') && !class_exists('PhpToken', false)) {
    class PhpToken extends Salient\Polyfill\PhpToken {}
}
