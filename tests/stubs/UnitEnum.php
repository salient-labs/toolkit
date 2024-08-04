<?php

if (\PHP_VERSION_ID < 80100 && !interface_exists('UnitEnum', false)) {
    interface UnitEnum
    {
        /** @return static[] */
        public static function cases(): array;
    }
}
