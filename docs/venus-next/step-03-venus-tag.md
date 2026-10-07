# Venus Next - Step 3: Venus tag

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

**Branch:** all Venus work lives on `venus-next`, not `main`. Branch from the latest `venus-next`
(it must already contain the steps this one depends on), and merge your finished step back into
`venus-next`. Do not merge into or open PRs against `main`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md` and `DESIGN.md` first.
- All game logic is in `modules/PGameXBody.php`. Game data lives in `misc/*.csv` (`|`-separated,
  `#set key=value` sets defaults for following rows). `npm run build:material` regenerates the
  `/* --- gen php begin <csvname> --- */` sections of `material.inc.php`; never hand-edit those.
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|...`. Tags are space-separated
  (`Space Venus`); the power tag is spelled `Energy`; events add `Event`.
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `mtFind("name", "Ishtar Mining")` returns
  a card id, `effect_playCard($color, $card_id)`, `getTrackerValue($color, "tagVenus")`,
  `evaluateExpression($expr, $color)`, `playability($color, $card_id)` (returns `MA_OK` or an error code),
  `tokens->setTokenState(...)`.
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available (from step 1)

`isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, Venus cards `card_main_213..261` (data, no
rules yet) in `misc/venus_material.csv`, `modules/tests/VenusTest.php`. Some cards already have tag
requirements in `pre` (e.g. Venus Governor `tagVenus>=2`) that fail to evaluate until this step.

## Background

Tags are counted in per-player trackers `tracker_tag<Tag>_<color>` defined in `misc/tracker_material.csv`
(`#set type=tracker badge` block, ~line 70). Playing a card increments `tag$tag` for each tag
(`PGameXBody.php` ~line 1973, corps ~line 2036) and emits `play_tag<Tag>` events (~line 2234). `tagVenus`
already exists as a tag type in the `#tags` block, but its counter `#tracker_tagVenus|Count of Venus tags`
is commented out. A CSS class `.tracker_tagVenus` already exists in `src/css/PlayerBoard.scss` (~line 238).

## Tasks

1. Un-comment `tracker_tagVenus|Count of Venus tags` in `misc/tracker_material.csv`. Decide whether to
   create it always or only with Venus on. Creating it always is simplest and harmless (Venus tags only
   come from Venus cards); if you gate it, the `evaluateTerm` lookup for `tagVenus` must still return 0
   when Venus is off. Pick one and test it.
2. `npm run build:material`.
3. Check that every place that iterates tag trackers handles Venus correctly:
   - `getCountOfUniqueTags` (~line 3189, used by Aridor's `play_newtag` and some milestones/awards).
   - Generalist / Specialist / Diversifier style counts (`getGeneralistCount` ~line 3246,
     `getSpecialistCount`) - make sure a Venus tag counts like any other tag.
   - Wild tag handling in `evaluateTerm` (~line 1234).
4. Client: if the player board shows a fixed list of tag counters, make sure Venus appears when the option
   is on. Look for `tracker_tagJovian` in `src/*.ts` and `terraformingmars_terraformingmars.tpl`. If it needs
   more than a trivial change, leave it for step 10 and note it in your summary.

## Tests (append to `modules/tests/VenusTest.php`)

- `testPlayVenusCardCountsTag` - play Ishtar Mining (`card_main_233`): `tagVenus` = 1.
- `testVenusGovernorCountsTwoTags` - play Venus Governor (`card_main_255`, tags `Venus Venus`): +2.
- `testVenusTagRequirement` - Sister Planet Support (`card_main_244`): `precondition` fails with no tags,
  passes with 1 Venus + 1 Earth, and with 1 Earth + 1 Wild.
- `testVenusTagTriggersPlayTagVenus` - a listener `play_tagVenus:m` on a tableau card fires when a Venus
  card is played (put any card on the tableau and override its `e` via `$m->token_types[...]`, or follow
  how `testListeners` in `GameTest.php` sets one up).
- `testUniqueTagsIncludesVenus` - `evaluateExpression("uniquetags", PCOLOR)` increases by 1 after playing
  the first Venus card.
- `testVenusTagZeroWithoutVenus` - `init(0,0,0)`: `evaluateExpression("tagVenus", PCOLOR)` is 0 (no
  exception).

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): count Venus tags`.

## Out of scope

Hoverlord/Venuphile (step 6), card rules (step 8).
