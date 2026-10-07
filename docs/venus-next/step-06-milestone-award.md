# Venus Next - Step 6: Hoverlord milestone and Venuphile award

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
- Maps: 0 Tharsis, 1 Elysium, 2 Hellas, 3 Vastitas Borealis, 4 Amazonis Planitia
  (`MA_OPTVALUE_MAP_*` in `material.inc.php`).
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Existing milestone/award tests to copy:
  `testClaimMilestone_Elysium1`, `testAward_Elysium1`, `testMilestone_Vastitas5_Farmer` in
  `modules/tests/GameTest.php` (~lines 419-596).
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

- Step 1: `isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, `misc/venus_material.csv`,
  `modules/tests/VenusTest.php`.
- Step 3: `tagVenus` counting (`evaluateExpression("tagVenus", $color)`).
- Existing term `resFloater` = floaters on the player's cards (`evaluateTerm`, ~line 1131).

## Rules decisions you need

- With Venus Next on, **every** map gets one extra milestone and one extra award (6 of each):
  - **Hoverlord** milestone: have at least 7 floaters on your cards. 5 VP like the others.
  - **Venuphile** award: most Venus tags in play.
- Limits are unchanged: at most 3 milestones claimed and 3 awards funded per game. Award funding costs are
  unchanged (8 / 14 / 20).

## Background

Milestones/awards are in `misc/proj_material.csv`: base set `milestone_{num}` / `award_{num}` (Tharsis,
nums 1-5), and per-map variants `milestone_{num}@mN` / `award_{num}@mN`. `doAdjustMaterial()` (~line 930)
replaces `X` with `X@m<current map>` and drops other variants. **An id with no `@m` variant stays on every
map**, so `milestone_6` / `award_6` defined once (no `@m`) appear on all maps. Look at the existing
`#Milestones` block: `#set pre=({r}>={min})`, columns `num|name|t|r|cost|text|min`.
Claim/fund limits: `Operation_claim.php` / `Operation_fund.php` (`$claimed >= 3`).

## Tasks

1. Add the two rows to `misc/venus_material.csv`. genmat (`misc/other/genmat.php`) reads **one header
   per file**, so the rows must use the card header
   (`num|name|t|r|a|e|cost|pre|tags|vp|deck|text|...`), not the `proj_material.csv` one, and `{min}`
   substitution is not available - write `pre` out. Copy the other `#set` lines of the base blocks
   (`id`, `php`, `location`, `count`, `create`) and set `pre`/`php` explicitly, e.g.:
   - `#set id=milestone_{num}` ... `6|Hoverlord|7|resFloater|||8|resFloater>=7|||||Having at least 7 floaters on your cards`
     with `php` `'vp'=>5`
   - `#set id=award_{num}` ... `6|Venuphile|8|tagVenus|||20||||||Having the most Venus tags in play.`
     with empty `pre` and `php`
   Then diff the generated `milestone_6` / `award_6` entries in `material.inc.php` against `milestone_1` /
   `award_1` and make sure they have the same keys (`r`, `cost`, `pre`, `vp`, `text`, `location`, `t`).
2. `createTokens()`: skip `milestone_6` and `award_6` when Venus is off.
3. Check everything that assumes exactly 5 milestones/awards (e.g. loops `1..5`, scoring, client layout
   in `src/GameXBody.ts` / `terraformingmars_terraformingmars.tpl`) and make it data-driven. Client layout
   polish can wait for step 10, but nothing may crash.
4. `npm run build:material`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testVenusOptionOnAddsHoverlordAndVenuphile` - for each map 0-4, `init($map, 0, 1)`: Hoverlord is in
  `display_milestones` and Venuphile in `display_awards`, alongside the map's 5 regular ones.
- `testVenusMilestonesAbsentWithoutVenus` - for each map 0-4, `init($map, 0, 0)`: neither exists.
- `testHoverlordClaim` - 6 floaters: claim op reports the milestone as not claimable; 7 floaters split
  over two cards: claimable; claiming gives the 5 VP at scoring.
- `testVenuphileAward` - PCOLOR 2 Venus tags, BCOLOR 1: PCOLOR first place.
- `testMaxThreeMilestonesStillEnforcedWithSix` - 3 claimed: the 4th (Hoverlord) returns
  `MA_ERR_MAXREACHED`.

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): Hoverlord milestone and Venuphile award`.

## Out of scope

Visual polish of the 6-wide milestone/award row (step 10).
