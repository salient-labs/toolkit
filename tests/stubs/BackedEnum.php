<?php

if (\PHP_VERSION_ID < 80100 && !interface_exists('BackedEnum', false)) {
    interface BackedEnum extends UnitEnum
    {
        /**
         * @param int|string $value
         * @return static
         */
        public static function from($value): self;

        /**
         * @param int|string $value
         * @return static|null
         */
        public static function tryFrom($value): ?self;
    }
}
