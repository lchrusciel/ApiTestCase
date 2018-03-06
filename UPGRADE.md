# ApiTestCase upgrade instructions

## Upgrading from v2 to v3

* JSON output is not escaped any more when comparing it to expected data. Turn on `ESCAPE_JSON` flag in your test configuration retain previous behaviour.

  NOTE: This flag exists for temporary BC between v2 and v3 and will be removed in v4. v4 will not escape JSON output.
