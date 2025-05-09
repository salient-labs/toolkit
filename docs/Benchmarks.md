# Benchmarks

Unscientific tests that have informed various decisions.

## Polling loops

e.g. when waiting for a `proc_open()` process to terminate:

| Statement           | CPU time   | % of elapsed time |
| ------------------- | ---------- | ----------------- |
| none                | 9984.34ms  | 99.84%            |
| `usleep(1)`         | 1234.08ms  | 12.34%            |
| `usleep(10)`        | 232.06ms   | 2.32%             |
| `usleep(100)`       | 94.29ms    | 0.94%             |
| `usleep(1000)`      | 25.15ms    | 0.25%             |
| **`usleep(10000)`** | **6.00ms** | **0.06%**         |
| `usleep(100000)`    | 2.34ms     | 0.02%             |
