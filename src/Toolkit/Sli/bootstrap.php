<?php declare(strict_types=1);

// PHP 8.0 tokens not defined in Polyfill/bootstrap.php
defined('T_ATTRIBUTE') || define('T_ATTRIBUTE', 10004);
defined('T_MATCH') || define('T_MATCH', 10005);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', 10006);

// PHP 8.1 tokens
defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 10007);
defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 10008);
defined('T_ENUM') || define('T_ENUM', 10009);
defined('T_READONLY') || define('T_READONLY', 10010);

// PHP 8.4 tokens
defined('T_PRIVATE_SET') || define('T_PRIVATE_SET', 10011);
defined('T_PROTECTED_SET') || define('T_PROTECTED_SET', 10012);
defined('T_PUBLIC_SET') || define('T_PUBLIC_SET', 10013);
defined('T_PROPERTY_C') || define('T_PROPERTY_C', 10014);

// PHP 8.5 tokens
defined('T_VOID_CAST') || define('T_VOID_CAST', 10015);
defined('T_PIPE') || define('T_PIPE', 10016);

// Single-character tokens
defined('T_OPEN_BRACE') || define('T_OPEN_BRACE', ord('{'));
defined('T_OPEN_BRACKET') || define('T_OPEN_BRACKET', ord('['));
defined('T_OPEN_PARENTHESIS') || define('T_OPEN_PARENTHESIS', ord('('));
defined('T_CLOSE_BRACE') || define('T_CLOSE_BRACE', ord('}'));
defined('T_CLOSE_BRACKET') || define('T_CLOSE_BRACKET', ord(']'));
defined('T_CLOSE_PARENTHESIS') || define('T_CLOSE_PARENTHESIS', ord(')'));
defined('T_AND') || define('T_AND', ord('&'));
defined('T_COMMA') || define('T_COMMA', ord(','));
defined('T_SEMICOLON') || define('T_SEMICOLON', ord(';'));
defined('T_EQUAL') || define('T_EQUAL', ord('='));
