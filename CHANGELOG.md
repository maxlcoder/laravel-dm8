# Changelog

## 2.0.0

- Require PHP 8.2+ and Illuminate 12; retain the 1.x tags for older applications.
- Adapt query and schema Grammar signatures, connection-aware Grammar constructors and Blueprint creation to Laravel 12.
- Preserve schema/table prefixes, including explicit table-prefix overrides.
- Remove insertion logic depending on fixed debug-backtrace frames; allocate custom sequences explicitly in DmEloquent.
- Bind returned keys as input/output integers with a positive native integer buffer; remove negative LOB input buffer lengths.
- Record query durations in milliseconds.
- Add isolated regression tests and PHP 8.2/8.3/8.4 CI.

Validation: mocked insert/schema tests and a real PHP 8.2 PDO_DM read-only output binding probe. No writes to a real DM database were performed; real insert, LOB and migration integration remains unverified.
