# Venus Next - Step 2: Venus parameter, `v` op, track bonuses, Air Scrapping

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

**Branch:** all Venus work lives on `venus-next`, not `main`. Branch from the latest `venus-next`
(it must already contain the steps this one depends on), and merge your finished step back into
`venus-next`. Do not merge into or open PRs against `main`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md` and `DESIGN.md` first.
- Server class chain: `terraformingmars.game.php` -> `PGameXBody` (all game logic, `modules/PGameXBody.php`)
  -> `PGameMachine` -> `PGameTokens` -> `PGameBasic` -> `Table`.
- Game data lives in `misc/*.csv` (`|`-separated, `#set key=value` sets defaults for the following rows).
  `npm run build:material` regenerates the parts of `material.inc.php` between
  `/* --- gen php begin <csvname> --- */` and `/* --- gen php end <csvname> --- */`. Never hand-edit those.
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|text|text_action|text_effect|text_vp|php`.
  `t`: 1 automated, 2 active, 3 event, 4 corp. `r` immediate op expression, `a` action, `e` effect,
  `pre` requirement (MathExpression), `vp` VP.
- Operation types map to `modules/operations/Operation_<type>.php` unless `misc/op_material.csv` sets a
  `class`. Lookup: `PGameXBody::getOperationInstance`.
- Op expressions: syntax at the top of `modules/OpExpression.php` (`/` or, `+` unordered and, `,` ordered
  and, `:` pay:get, `?` optional, prefix number = count, e.g. `2t`).
- Tokens: global params `tracker_t`, `tracker_o`, `tracker_w` (type `param`, location `params`, defined in
  `misc/tracker_material.csv` "terraforming parameters" block), per-player trackers `tracker_<x>_<color>`.
- Tests: `npm run test` (PHPUnit, ~2s), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Typical test:
  `$m = (new GameUT())->init(0, 0, 1); $m->push(PCOLOR, "v"); $m->st_gameDispatch();` then assert on
  `$m->tokens->getTokenState("tracker_v")` / `$m->getTrackerValue(PCOLOR, "tr")`.
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.
- Shell: Git Bash on Windows.

## Already available (from step 1)

`isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, `misc/venus_material.csv` with material
section `venus_material`, deck `Venus`, `modules/tests/VenusTest.php`.

## Rules decisions you need

- Venus scale 0-30% in 2% steps (15 steps). Each step: raising player +1 TR.
- Bonuses: reaching 8% -> draw 1 card; reaching 16% -> +1 TR (on top of the step's TR).
- Venus is **not** part of the multiplayer end-of-game condition.
- Standard project Air Scrapping: 15 M€, raise Venus 1 step.
- Aphrodite (step 9) gains 2 M€ **per step** Venus is raised by anyone, so a per-step trigger is needed.

## Tasks

1. **Tracker.** `misc/tracker_material.csv`, terraforming parameters block (next to `tracker_o`):
   `tracker_v|Venus|0|'max'=>30`. Only create it with Venus on: in `createTokens()` skip `tracker_v` when
   `!isVenusVariant()` (same pattern as `card_stanproj_8`, ~line 845).
2. **Track bonuses.** Existing bonuses (`param_o_8`, `param_t_0`, `param_t_n24`, ...) are **hand-written**
   at the top of `$this->token_types` in `material.inc.php` (~line 124, before the first gen section, under
   `#parameters bonuses`). `effect_increaseParam` (~line 2339) looks them up as `param_{type}_{value}` and
   queues their `r`. Add, in the same style:
   `"param_v_8" => ["r" => "draw", "param" => "v", "value" => 8]` and
   `"param_v_16" => ["r" => "tr", "param" => "v", "value" => 16]`.
3. **Op `v`.** Add `v|Raise Venus` to `misc/op_material.csv` near `t`/`o` (check which `#set class=` block
   they are in, or none). Create `modules/operations/Operation_v.php`, a copy of `Operation_t.php`
   (`effect_increaseParam($owner, "v", $inc, 2)`), with prompt "Venus is already at maximum: you may
   proceed with this action without raising Venus further".
4. **Trigger.** In `effect_increaseParam` (~line 2305), when `$type == "v"`, call
   `$this->triggerEffect($color, "raise_v", ...)` once **per step actually raised** (after capping at max).
   Look at how `triggerEffect` is called for `place_ocean` to match the arguments.
5. **Requirement step size.** In `evaluateTerm` (~line 1180) the `param` branch doubles `mods` for `t`.
   Do the same for `v` (2% per step).
6. **End of game unaffected.** `getTerraformingProgression()` (~line 323) and `isEndOfGameAchived()` must
   keep using only t/o/w. Add `getVenusProgression()` (0-100) for later UI use; do not wire it in yet.
7. **Air Scrapping.** In `misc/venus_material.csv` add a standard project section like the Colonies one
   (`misc/colo_material.csv` ~line 94: `#set id=card_stanproj_{num}`, `#set location=display_main`,
   `#set create=1`, `#set type=stanproj`): `9|Air Scrapping|0|v|...|15|...` - match the column layout of
   that file's header and the base standard projects in `misc/proj_material.csv`. In `createTokens()`
   skip `card_stanproj_9` when Venus is off. Check `Operation_turn::getStandardActions` (and
   `Operation_stan`) to see whether standard projects are listed by token location or by a hard-coded
   list, and make Air Scrapping selectable.
8. `npm run build:material`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testRaiseVenusIncreasesTrackAndTR` - `v`: tracker_v 0 -> 2, TR +1.
- `testRaiseVenus2Steps` - `2v`: 4, TR +2.
- `testVenusBonusAt8DrawsCard` - tracker_v = 6, `v`: hand +1.
- `testVenusBonusAt16GivesTR` - tracker_v = 14, `v`: TR +2.
- `testVenusCapsAt30` - tracker_v = 28, `2v`: 30, TR +1; at 30 the op `requireConfirmation()` is true.
- `testRaiseVenusFiresTriggerPerStep` - register a listener on `raise_v` (see `setListeners` in GameUT
  or put a temporary card on the tableau with `e` = `raise_v:m:this:any`), `2v` -> listener runs twice.
- `testVenusDoesNotAffectEndOfGame` - t/o/w at max, Venus 0: `isEndOfGameAchived()` true. Only Venus at
  max: false.
- `testVenusRequirementTermUsesStepsOf2` - `evaluateExpression("v>=10", ...)` with `mods` 2 is true at 6.
- `testAirScrappingOnlyWithVenus` - `card_stanproj_9` exists with Venus on, not with Venus off; with 15 M€
  playing it raises Venus and leaves 0 M€.
- `testNoVenusTrackerWithoutVenus` - `init(0,0,0)`: no `tracker_v` token.

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): add Venus scale, v operation and Air Scrapping`.

## Out of scope

Venus requirements on cards (step 4), World Government (step 5), client display (step 10).
