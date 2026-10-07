# Venus Next - Step 5: World Government Terraforming (solar phase) and solo win rule

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

**Branch:** all Venus work lives on `venus-next`, not `main`. Branch from the latest `venus-next`
(it must already contain the steps this one depends on), and merge your finished step back into
`venus-next`. Do not merge into or open PRs against `main`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md`, `DESIGN.md` and `modules/DbMachine.md` first.
- All game logic is in `modules/PGameXBody.php`. The "machine" is a stack/queue of operations
  (`$this->machine->queue(type, count, mcount, color)`, `$this->push(color, expr)`); states are generic and
  the machine drives the game.
- Operation types map to `modules/operations/Operation_<type>.php` unless `misc/op_material.csv` sets a
  `class`. Base class `AbsOperation`: `effect()`, `isVoid()`, `argPrimaryDetails()` (choices, built with
  `$this->game->createArgInfo(...)`), `getPrimaryArgType()` (`'enum'`, `'token'`, `''`), `getPrompt()`.
  Good examples to copy: `Operation_q.php` (choose one of several options), `Operation_t.php`, `Operation_w.php`.
- `npm run build:material` regenerates the `/* --- gen php begin <csvname> --- */` sections of
  `material.inc.php` from `misc/*.csv`; never hand-edit those.
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `push`, `st_gameDispatch`,
  `fakeUserAction($op, $target)`, `machine->getTopOperations($color)`, `tokens->setTokenState`,
  `getTrackerValue`. Solo tests: see `testSoloSetup` in `modules/tests/GameTest.php` (~line 710) for how a
  1-player `GameUT` is built (`_setPlayerBasicInfoFromColors([PCOLOR])`).
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

- Step 1: `isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, `modules/tests/VenusTest.php`.
- Step 2: `tracker_v` (0..30), op `v` (`Operation_v`), `effect_increaseParam($color, $type, $steps,
  $perstep, $options)` fires `raise_v` once per Venus step and queues track bonuses (`param_v_8`,
  `param_v_16`, plus the Mars ones like `param_o_8` -> temperature, `param_t_0` -> ocean).

## Rules decisions you need

- Generation end: production phase, then **solar phase**: (1) game end check - if the game ends, go to
  final scoring and skip the rest; (2) **World Government Terraforming** (Venus Next only); (3) colony
  production (Colonies only); then the next generation starts.
- WGT: the **first player of the generation that just ended** chooses one global parameter that is not
  maxed (temperature, oxygen, ocean or Venus) and raises it one step. This gives **no TR and no bonuses of
  any kind**: no track bonuses (oxygen 8% -> temperature, temperature -> heat production / ocean, Venus
  8% / 16%) and no tile placement bonuses (hex resources, 2 M€ per adjacent ocean). The first player
  chooses where the ocean goes.
- WGT raising Venus **does** trigger Aphrodite (the `raise_v` event still fires).
- Solo with Venus Next (standard flavour, `var_solo_flavour == 0`): the player wins only if **all four**
  parameters are maxed, Venus included. 14 TR, 14 generations (12 with Prelude) as before. WGT runs every
  generation; the solo player is always first player. In the last generation WGT is skipped because the end
  check comes first. The TR63 flavour is unchanged (Venus irrelevant).
- Open: should cards that trigger on "any ocean placed" (Arctic Algae, Lakefront Resorts-style effects)
  trigger for a WGT ocean? Not decided - **ask the user** before implementing; until then keep the existing
  `place_ocean` trigger behaviour and note it in your summary.

## Background

`effect_endOfTurn()` (`PGameXBody.php` ~line 2551):

```php
$this->effect_production();
// solar phase
// step 1: end of game check
if ($this->isEndOfGameAchived()) { ... queue lastforest, finalscoring; return null; }
// step 2: world goverment: venus only
// step 3: colony production
$this->effect_colonyProduction();
$current_player_id = $this->getCurrentStartingPlayer();
$player_id = $this->getPlayerAfter($current_player_id);
$this->setCurrentStartingPlayer($player_id);
$this->machine->queue("research", 1, 1, $this->custom_getPlayerColorById($player_id));
```

In solo `isEndOfGameAchived()` returns true when `tracker_gen >= getLastGeneration()`, so WGT is naturally
skipped in the last solo generation. Colony production is immediate, while WGT needs a player choice, so
the colony step must run **after** WGT resolves. Queue it as an operation (or queue `wgt` first and move
colony production into an op that runs after it). Keep research last.

Tile placement bonuses are applied in `PGameXBody::effect_placeTile()` (~line 2053: hex `r` bonus and
"2 M€ per adjacent ocean") and the ocean TR comes from `effect_increaseParam` via `Operation_w`.
`effect_increaseParam` (~line 2305) calls `effect_incTerraformingRank` and the bonus loop.

## Tasks

1. `effect_increaseParam`: support `$options["wgt"] = true` - skip `effect_incTerraformingRank` and the
   bonus loop, use the log message "World Government raises ${token_name} ..." and keep firing `raise_v`.
2. `effect_placeTile`: support the same flag (skip the hex bonus and the adjacent-ocean M€). Thread it
   through `Operation_w` / `AbsOperationTile` (e.g. a `wgt` op parameter such as `w(wgt)`, or a dedicated op
   `wgtw` that extends `Operation_w`). Choose whatever fits the op-parameter conventions you find.
3. New op `wgt` (`misc/op_material.csv`: `wgt|World Government Terraforming|${you} must choose a global
   parameter for the World Government to raise`) and `modules/operations/Operation_wgt.php`: choices are the
   non-maxed params among `t`, `o`, `w`, `v`; on choice, raise it with the WGT flag (ocean: push the WGT
   ocean placement for the same player). Void when all four are maxed (then it auto-skips). Not undoable
   past the choice is fine.
4. `effect_endOfTurn()`: when `isVenusVariant()` and the game is not ending, queue `wgt` for the starting
   player of the generation that just ended (**before** `setCurrentStartingPlayer` advances it), then
   colony production, then research. Make sure Colonies-only games keep their current behaviour.
5. Solo win: add `isSoloTerraformingComplete()` = t/o/w at max, and with Venus on also `tracker_v` at max.
   Use it in `effect_finalScoring()` (~line 2690, the standard-flavour branch that checks
   `getTerraformingProgression() >= 100`) and update its log text to mention Venus when Venus is on.
6. `npm run build:material`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testWgtQueuedOnlyWithVenus` - call `effect_endOfTurn()`: no `wgt` op without Venus; with Venus, one
  `wgt` op for the starting player.
- `testWgtRaisesVenusWithoutTR` - resolve `wgt` choosing Venus: `tracker_v` +2, TR unchanged.
- `testWgtVenusBonusNotGiven` - `tracker_v` = 6, WGT Venus: no card drawn.
- `testWgtTemperatureNoBonus` - temperature -26, WGT temperature: -24 and heat production unchanged.
- `testWgtOxygenNoTemperatureBonus` - oxygen 7, WGT oxygen: 8 and temperature unchanged.
- `testWgtOceanNoPlacementBonus` - WGT ocean on an ocean hex with a plant bonus next to an existing
  ocean: plants, M€ and TR unchanged; `tracker_w` +1.
- `testWgtSkipsMaxedParams` - Venus at 30: `v` not offered; all four maxed: op void.
- `testWgtFiresRaiseV` - a `raise_v` listener runs once when WGT raises Venus.
- `testWgtNotRunWhenGameEnds` - t/o/w maxed before `effect_endOfTurn()`: final scoring queued, no `wgt`.
- `testWgtRunsBeforeColonyProduction` - Venus and Colonies on: colony track levels are unchanged until
  `wgt` resolves, then increase, then `research` is next.
- `testSoloWgtEachGenerationBeforeLast` - solo with Venus, end of generation 13: `wgt` queued.
- `testSoloWgtSkippedInLastGeneration` - solo with Venus, end of generation 14: no `wgt`, final scoring.
- `testSoloVenusWinRequiresVenusMaxed` - Mars maxed, Venus 28: `isSoloTerraformingComplete()` false;
  Venus 30: true.
- `testSoloWithoutVenusWinUnchanged` - Venus off, Mars maxed: true.
- `testSoloTR63IgnoresVenus` - TR63 flavour, Venus on, TR 63 and Venus 0: win.

## Done when

- All tests pass (including existing Colonies tests), `npm run lint:php` clean.
- Commit: `feat(venus): World Government Terraforming and solo Venus win rule`.

## Out of scope

Aphrodite itself (step 9), client prompt styling (step 10).
