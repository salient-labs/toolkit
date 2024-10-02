<?php

if (\PHP_VERSION_ID < 80000 && !class_exists('ValueError', false)) {
    class ValueError extends Error {}
}
