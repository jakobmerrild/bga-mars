# Venus Next - Step 4: Venus requirements and requirement modifiers

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
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|...`. `pre` is a MathExpression requirement,
  e.g. `o<=6`, `t>=-12`, `tagScience>=3`.
- Operation types map to `modules/operations/Operation_<type>.php` unless `misc/op_material.csv` sets a
  `class` (e.g. `pdelta` uses `Operation_R`, which just adds to the tracker of the same name).
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `mtFind("name", ...)`,
  `precondition($color, $card_id)` (returns `MA_OK` or `MA_ERR_PREREQ`), `effect_playCard`,
  `tokens->setTokenState("tracker_v", 6)`, `executeImmediately($color, "2pdelta")`.
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

- Step 1: `isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, Venus cards in
  `misc/venus_material.csv` (no `pre` for Venus % requirements yet), `modules/tests/VenusTest.php`.
- Step 2: `tracker_v` param (0..30, 2% per step); `evaluateTerm` already doubles `mods` for `v`.
- Step 3: `tagVenus` counting.

## Rules decisions you need

- Adaptation Technology (#153, `r`=`2pdelta`), Inventrix (corp #6, `r`=`2pdelta`) and Special Design
  (#206, `e`=`onPre_delta:2;...`) modify **all** global requirements, **including Venus**.
- Morning Star Inc. (Venus corp, `card_corp_17`): "Your Venus requirements are +/- 2 steps" - Venus only,
  and it **stacks** with the general modifiers.
- "+/- 2 steps, your choice" is checked today by trying the requirement with `+delta` and with `-delta`.

## Background

`evaluatePrecondition()` (`PGameXBody.php` ~line 995): evaluates `pre`; if false, sums
`tracker_pdelta_<color>` and any `onPre_delta` listener outcomes into `$delta`, then re-evaluates with
`["mods" => $delta]` and `["mods" => -$delta]`. In `evaluateTerm` (~line 1180) the `param` branch adds
`mods` to every param term (`t` doubled, and after step 2 `v` doubled). So the general modifiers already
reach Venus once `v` is a param - verify this rather than assume it.

## Tasks

1. **Venus % requirements.** In `misc/venus_material.csv` fill `pre` for every card with a Venus
   requirement (source: `misc/venus_next_cards.json`, `requirement.type == "venus"`): `min` -> `v>=N`,
   `max` -> `v<=N`. Cards: 227 (10), 233 (8), 238 (12), 240 (10), 243 (max 14), 246 (max 10), 249 (12),
   251 (6), 253 (6), 256 (10), 259 (18), 260 (12), 261 (16). Also Aerosport Tournament (214):
   `resFloater>=5` (the `resFloater` term already exists).
2. **Morning Star delta.** Add a per-player tracker `tracker_pdeltav` in `misc/tracker_material.csv`
   next to `tracker_pdelta` (~line 30), and op `pdeltav|Increase Venus Requirements Delta` in
   `misc/op_material.csv` next to `pdelta` (same `Operation_R` class block).
3. In `evaluatePrecondition`, also read `tracker_pdeltav_<color>` into `$vdelta` and evaluate with
   `["mods" => $delta, "vmods" => $vdelta]` and the negated pair. Run the re-check when either delta is
   non-zero. In `evaluateTerm`, for `v` add `mods + vmods` (then double); for `t/o/w` add only `mods`.
4. Set Morning Star Inc.'s rules in `misc/venus_material.csv`: `r` = `2pdeltav` (the reveal-3-Venus-cards
   first action is step 9; leave it).
5. `npm run build:material`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testVenusMinRequirement` - Neutralizer Factory (`card_main_240`, `v>=10`): fails at 8, passes at 10.
- `testVenusMaxRequirement` - Rotator Impacts (`card_main_243`, `v<=14`): passes at 14, fails at 16.
- `testAerosportNeedsFiveFloaters` - fails with 4 floaters on a card, passes with 5 (create resources with
  how `GameTest::test_res` adds resources, or `executeImmediately($color, "5res", $card_id)` on a floater card).
- `testAdaptationTechnologyAppliesToVenus` - `tracker_pdelta` = 2: `v>=10` passes at 6, `v<=14` passes at 18.
- `testSpecialDesignAppliesToVenus` - Special Design (#206) on the tableau: a `v>=10` card passes at 6.
- `testInventrixAppliesToVenus` - Inventrix owner (play `card_corp_6`): `v>=10` passes at 6.
- `testVenusDeltaAppliesOnlyToVenus` - `tracker_pdeltav` = 2: `v>=10` passes at 6, but a `t>=-12` card
  (e.g. Eos Chasma National Park) still fails at -16.
- `testVenusDeltaWorksForMaxReq` - `tracker_pdeltav` = 2: Spin-Inducing Asteroid (`v<=10`) passes at 14.
- `testMorningStarStacksWithAdaptationTechnology` - both deltas 2: `v>=10` passes at 2, fails at 0.
- `testMorningStarCorpSetsDelta` - play `card_corp_17`: `tracker_pdeltav` = 2.

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): Venus requirements and requirement modifiers`.

## Out of scope

Morning Star's reveal-3-Venus-cards first action (step 9).
