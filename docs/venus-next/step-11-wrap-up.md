# Venus Next - Step 11: wrap-up

You are finishing the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

**Branch:** all Venus work lives on `venus-next`, not `main`. Branch from the latest `venus-next`
(it must already contain the steps this one depends on), and merge your finished step back into
`venus-next`. Do not merge into or open PRs against `main`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client.
  Read `CLAUDE.md` first (commands, generated files, changelog format, staging).
- `npm run predeploy` = build (ts, scss, material) + `lint:php` + PHPUnit + mocha. Run it before committing.
- `CHANGELOG.md` has user-facing entries per deployed version: `## <date> (v<version>)`, committed as
  `docs: change log for v<version>`. Look at existing entries for tone.
- Staging for BGA Studio: `npm run build:ts && npm run build:scss && npm run stage --
  terraformingmarsjmerrild` (recreates `dist/terraformingmarsjmerrild/`; never edit `dist/`).

## Already done

Steps 1-10: option `111` "Expansion: Venus Next" (alpha), Venus track, `v` op, Air Scrapping, Venus tag,
requirements and modifiers, World Government Terraforming, solo Venus win rule, Hoverlord and Venuphile,
off-Mars Venus cities, 49 project cards, 5 corporations, client UI.

## Tasks

1. Run `npm run predeploy`; fix anything that fails (do not skip tests).
2. Review `modules/tests/VenusTest.php` against the test lists in `VENUS_NEXT_PLAN.md`; add any missing
   test or note why it was dropped.
3. Check `misc/venus_next_cards.json` against `misc/venus_material.csv` once more (a test from step 1
   compares cost and name; also eyeball tags, VP and requirements).
4. Hidden-information check: grep the new code for `notifyAllPlayers` with hand/deck contents (reveal-until,
   Sponsored Academies, Celestic, Morning Star). See `TODO.md` "Private info leak" for the known pattern.
5. `CHANGELOG.md`: add an entry "Venus Next expansion (alpha)" under a new unreleased heading (ask the user
   for the version number if it is not obvious).
6. `TODO.md`: add a "Venus Next" section with the known gaps: card art, Venus board graphics, any
   open questions found during implementation (e.g. whether card effects trigger on World Government
   oceans), anything from step summaries marked as deferred.
7. Keep the option flagged `"alpha": true` in `gameoptions.json` and `misc/other/gameoptions.json`.
8. Update `docs/venus-next/README.md` status checkboxes.
9. Stage for BGA Studio and tell the user to upload and play a few test games.

## Done when

- `npm run predeploy` passes.
- Commits: `docs(venus): changelog and TODO for Venus Next alpha` (plus any fix commits).
