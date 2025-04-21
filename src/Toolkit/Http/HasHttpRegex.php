<?php declare(strict_types=1);

namespace Salient\Http;

/**
 * @internal
 */
interface HasHttpRegex
{
    public const HTTP_TOKEN = '(?:(?i)[-0-9a-z!#$%&\'*+.^_`|~]++)';
    public const HTTP_TOKEN_REGEX = '/^[-0-9a-z!#$%&\'*+.^_`|~]++$/iD';

    public const HTTP_HEADER_FIELD_REGEX = <<<'REGEX'
/ ^
(?(DEFINE)
  (?<token> [-0-9a-z!#$%&'*+.^_`|~]++ )
  (?<field_vchar> [\x21-\x7e\x80-\xff]++ )
  (?<field_content> (?&field_vchar) (?: \h++ (?&field_vchar) )*+ )
)
(?:
  (?<name> (?&token) ) (?<bad_whitespace> \h++ )?+ : \h*+ (?<value> (?&field_content)? ) |
  \h++ (?<extended> (?&field_content)? )
)
(?<carry> \h++ )?
$ /ixD
REGEX;

    public const HTTP_HEADER_FIELD_NAME_REGEX = self::HTTP_TOKEN_REGEX;
    public const HTTP_HEADER_FIELD_VALUE_REGEX = '/^([\x21-\x7e\x80-\xff]++(?:\h++[\x21-\x7e\x80-\xff]++)*+)?$/D';

    public const URI_REGEX = <<<'REGEX'
` ^
(?(DEFINE)
  (?<unreserved> [-a-z0-9._~] )
  (?<sub_delims> [!$&'()*+,;=] )
  (?<pct_encoded> % [0-9a-f]{2} )
  (?<reg_char> (?&unreserved) | (?&pct_encoded) | (?&sub_delims) )
  (?<pchar> (?&reg_char) | [:@] )
)
(?: (?<scheme> [a-z] [-a-z0-9+.]* ) : )?+
(?:
  //
  (?<authority>
    (?:
      (?<userinfo>
        (?<user> (?&reg_char)* )
        (?: : (?<pass> (?: (?&reg_char) | : )* ) )?
      )
      @
    )?+
    (?<host> (?&reg_char)*+ | \[ (?<ipv6address> [0-9a-f:]++ ) \] )
    (?: : (?<port> [0-9]+ ) )?+
  )
  # Path after authority must be empty or begin with "/"
  (?= / | \? | \# | $ ) |
  # Path cannot begin with "//" except after authority
  (?= / ) (?! // ) |
  # Rootless paths can only begin with a ":" segment after scheme
  (?(<scheme>) (?= (?&pchar) ) | (?= (?&reg_char) | @ ) (?! [^/:]++ : ) ) |
  (?= \? | \# | $ )
)
(?<path> (?: (?&pchar) | / )*+ )
(?: \? (?<query>    (?: (?&pchar) | [?/] )* ) )?+
(?: \# (?<fragment> (?: (?&pchar) | [?/] )* ) )?+
$ `ixD
REGEX;

    public const SCHEME_REGEX = '/^[a-z][-a-z0-9+.]*$/iD';
    public const HOST_REGEX = '/^(([-a-z0-9!$&\'()*+,.;=_~]|%[0-9a-f]{2})++|\[[0-9a-f:]++\])$/iD';
    public const AUTHORITY_FORM_REGEX = '/^(([-a-z0-9!$&\'()*+,.;=_~]|%[0-9a-f]{2})++|\[[0-9a-f:]++\]):[0-9]++$/iD';
}
