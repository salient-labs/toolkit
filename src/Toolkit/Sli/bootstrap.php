<?php declare(strict_types=1);

// PHP 8.0
defined('T_ATTRIBUTE') || define('T_ATTRIBUTE', 10001);
defined('T_MATCH') || define('T_MATCH', 10002);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', 10003);
defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 10004);
defined('T_NAME_RELATIVE') || define('T_NAME_RELATIVE', 10005);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', 10006);

// PHP 8.1
defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 10007);
defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 10008);
defined('T_ENUM') || define('T_ENUM', 10009);
defined('T_READONLY') || define('T_READONLY', 10010);

// PHP 8.4
defined('T_PROPERTY_C') || define('T_PROPERTY_C', 10011);

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
