# PR #209 Review: Add PHPUnit-like contextual diff visualization for API test failures

## Summary

This PR introduces a `ContextualDiffRenderer` that replaces the existing
`Diff_Renderer_Text_Unified` renderer when API response assertions fail. Instead
of showing the full unified diff, it shows only changed lines with 3 lines of
context, adds color coding (red for expected/missing, green for actual/unexpected),
displays the expected file path, and is pattern-aware (skips lines matching
phpmatcher patterns).

The feature addresses a real pain point: large response bodies produce unwieldy
diffs that are hard to scan.

---

## Issues & Concerns

### 1. Matcher error message is dropped (Bug)

The current code on master (`src/ApiTestCase.php:185`):
```php
self::fail($matcher->getError() . \PHP_EOL . $diff->render(new \Diff_Renderer_Text_Unified()));
```

The PR changes this to:
```php
self::fail($diff->render($renderer));
```

This **removes the PHPMatcher error message entirely**. The matcher error is
valuable -- it tells the user *why* matching failed (e.g.,
`Value 'foo' does not match pattern '@integer@'`). The contextual diff shows
*where* the difference is, but not the *semantic* matcher-level reason. Both
pieces of information are useful. The commit message says this was intentional
("Removes redundant matcher error messages"), but it's not redundant -- the diff
and the matcher error convey different information.

**Recommendation:** Preserve the matcher error:
```php
self::fail($matcher->getError() . \PHP_EOL . $diff->render($renderer));
```

### 2. Pattern-matching logic has edge cases with unequal line counts

In the `replace` branch of the renderer, expected and actual lines are zipped by
index:

```php
for ($lineIndex = 0; $lineIndex < $maxLines; $lineIndex++) {
    $expectedLine = $expectedLines[$lineIndex] ?? '';
    $actualLine = $actualLines[$lineIndex] ?? '';
    ...
}
```

This positional matching is fragile when the expected and actual sides have
different line counts within a replacement block. If a block has 3 expected lines
and 5 actual lines, lines are paired 1:1 by index, with the extra actual lines
matched against empty strings. This could produce misleading output -- a line
might "match" a pattern it shouldn't be paired with, or be shown as a diff
against an empty string rather than its true counterpart.

**Recommendation:** For `replace` blocks, first check if *all* expected lines
have pattern matches against their positional counterparts, and only suppress
them if the entire block matches. Otherwise, fall through to the standard
delete+insert rendering.

### 3. ANSI colors in `self::fail()` messages are problematic

`isColorSupported()` checks `posix_isatty(STDOUT)`, but `self::fail()` messages
go through PHPUnit's assertion system, not directly to STDOUT. So
`posix_isatty(STDOUT)` is checking the wrong file descriptor for this use case.
When running PHPUnit with `--colors=always` or in a terminal,
`posix_isatty(STDOUT)` returns true, and ANSI escape codes will be embedded in
the failure message. PHPUnit may not strip them properly in all output contexts
(IDE test runners, CI logs, etc.).

**Recommendation:** Either always disable colors (relying on `+`/`-` prefixes
for visual differentiation), or provide an explicit opt-in mechanism rather than
auto-detecting based on STDOUT.

### 4. Integration tests are too loose

The integration tests catch `AssertionFailedError` and check for substrings like
`-` and `+` in the message. These assertions are very weak -- a `-` or `+` could
appear in virtually any error message. More specific assertions would make these
tests more meaningful:

- Check for `@@` hunk headers
- Check for specific expected/actual line content from the diff
- Assert that `Expected file:` contains the actual path to the fixture file

### 5. `\PHP_EOL` vs `"\n"` inconsistency

The `Diff` is created by splitting on `\PHP_EOL`, but the renderer outputs lines
joined with `"\n"`. On Windows, `PHP_EOL` is `\r\n`, so the split would differ
from the join. The current master code has the same inconsistency, so this isn't
new to this PR, but it's worth being aware of.

### 6. Minor: `context` option in `Diff` constructor is a no-op

The PR passes `['context' => 3]` to the `Diff` constructor. The `phpspec/php-diff`
library already defaults to `context = 3` in
`Diff_SequenceMatcher::getGroupedOpcodes()`, so this does nothing. Consider
removing it to avoid implying it's configurable here, or keep it as explicit
documentation of intent.

---

## Style Notes

- The class has extensive PHPDoc blocks. The project's existing code uses minimal
  docblocks (e.g., `ApiTestCase.php`). Consider aligning with the project's style.
- The test method names `testDiffVisualizationShowsContextAndColors` reference
  "Colors" but colors won't be present in most test environments.

---

## Verdict

The core idea is sound and addresses a real usability issue. The implementation
is generally well-structured. I'd recommend addressing these items before merging:

1. **Restore the matcher error message** -- dropping it loses actionable
   debugging info.
2. **Reconsider ANSI colors in `self::fail()` messages** -- they may produce
   garbled escape codes in environments that don't handle them properly.
3. **Strengthen integration test assertions** to validate actual diff content,
   not just presence of `+`/`-`.
4. **Review the pattern-matching edge case** with unequal line counts in
   replacement blocks.
