# Environment variables

## `.env` file syntax

Non-empty lines in a `.env` file may contain either a shell-compatible variable
assignment (see below for limitations) or a comment.

Example:

```shell
LANG=en_US.UTF-8
TZ=Australia/Sydney

# app_secret is parsed as: '^K&4nnE
app_client_id=d8f024b9-1dfb-4dde-8f29-db98eefa317c
app_secret=''\''^K&4nnE'
```

- Unquoted values cannot contain unescaped whitespace, `"`, `'`, `$`, backticks,
  or glob characters (`*`, `?`, `[`, `]`).
- Quoted values must be fully enclosed by one pair of single or double quotes.
- Double-quoted values cannot contain `"`, `$`, or backticks unless they are
  escaped.
- Single-quoted values may contain single quotes if this syntax is used: `'\''`
- Variable expansion and command substitution are not supported.
- Comment lines must start with `#`.
