## Using the CLI utility

`salient/toolkit` includes code generators and other command-line tools you can
use by running `sli` from your project's `vendor/bin` directory.

For a list of available subcommands, run `sli` with no arguments. For usage
information, run `sli help <subcommand>` or add `--help` to any subcommand.

### Environment variables

To make it easier to work with PHP namespaces on the command line, the following
values are taken from the environment:

| Variable             | Description                                         | Example              |
| -------------------- | --------------------------------------------------- | -------------------- |
| `DEFAULT_NAMESPACE`  | Applied to unqualified class names                  | `Acme\Sync\Entity`   |
| `PROVIDER_NAMESPACE` | Applied to unqualified `--provider` class names     | `Acme\Sync\Provider` |
| `BUILDER_NAMESPACE`  | Overrides `DEFAULT_NAMESPACE` for `Builder` classes | `Acme\Builder`       |
| `FACADE_NAMESPACE`   | Overrides `DEFAULT_NAMESPACE` for `Facade` classes  | `Acme\Facade`        |
| `TESTS_NAMESPACE`    | Overrides `DEFAULT_NAMESPACE` for PHPUnit tests     | `Acme\Tests`         |
