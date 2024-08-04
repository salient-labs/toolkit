<?php

if (\PHP_VERSION_ID < 80000) {
    if (interface_exists('Stringable', false)) {
        return;
    }

    interface Stringable
    {
        /** @return string */
        public function __toString();
    }
}
