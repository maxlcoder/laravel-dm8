# Changelog

## 1.2.1

- Restore omitted, null and empty `insertGetId()` return-column compatibility using a plain INSERT and the write PDO connection's `lastInsertId()`.
- Preserve explicit return-column RETURNING behavior, Eloquent primary keys, custom sequences and LOB handling.
- Add regression coverage for identity retrieval, input bindings and read/write connection selection.

## 1.2.0

Laravel 10 / PHP 8.2 compatibility line; no application-level patches required.

- Remove fixed debug-backtrace frame access from insert SQL compilation and processing.
- Allocate configured DmEloquent sequences explicitly, preserving supplied primary keys.
- Use positive native integer buffers and input/output flags for returned keys.
- Remove negative input LOB buffer lengths and correct query duration units.
- Add regression tests for query/schema SQL, custom keys, sequences and LOB bindings.

Validated with mocks/SQLite on PHP 8.2. Real database writes remain unverified by explicit user preference. Laravel 12 continues to use 2.x.
