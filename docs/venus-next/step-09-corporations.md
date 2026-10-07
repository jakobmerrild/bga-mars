# Venus Next - Step 9: the five Venus corporations

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

**Branch:** all Venus work lives on `venus-next`, not `main`. Branch from the latest `venus-next`
(it must already contain the steps this one depends on), and merge your finished step back into
`venus-next`. Do not merge into or open PRs against `main`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md`, `DESIGN.md` and `modules/DbMachine.md` first.
- All game logic is in `modules/PGameXBody.php`. Game data lives in `misc/*.csv` (`|`-separated,
  `#set key=value` sets defaults for following rows). `npm run build:material` regenerates the
  `/* --- gen php begin <csvname> --- */` sections of `material.inc.php`; never hand-edit those.
- Corp rows (`t` = 4): `cost` is the **negative** starting M€, `r` = starting production/resources run when
  the corp is played (`effect_playCorporation`, ~line 2004), `a` = action, `e` = effect, `php`
  `'a1'=>'<op>'` = mandatory first action (handled in `modules/operations/Operation_turn.php` ~line 37;
  Inventrix uses `'a1'=>'3draw'`, Poseidon `'a1'=>'colony'`), `'holds'=>'Floater'`.
- Effects: `event:outcome[:context[:any]]`, `:any` = also fire on other players' events (Arctic Algae
  `place_ocean:2p:this:any`). Events are fired with `triggerEffect($color, $event, $card)` (~line 1555).
- Operation types map to `modules/operations/Operation_<type>.php` or a class in `misc/op_material.csv`.
  Base class `AbsOperation` (`effect`, `isVoid`, `argPrimaryDetails` via `createArgInfo`,
  `getPrimaryArgType`). Card actions: `Operation_activate.php` pushes the card's `a` and sets the card state
  to `MA_CARD_STATE_ACTION_USED` (3); unused is `MA_CARD_STATE_ACTION_UNUSED` (2). States reset each
  generation.
- Hidden information: hands, deck and discard must not leak. Cards revealed from the deck are public in the
  physical game, so logging them is fine; cards taken into hand use the private move helpers (`_private`).
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `effect_playCorporation($color, $id,
  false)`, `effect_playCard`, `push`, `st_gameDispatch`, `fakeUserAction`, `machine->getTopOperations`,
  `tokens->moveToken($id, "deck_main", $state)` to stack the deck (check how `pickTokensForLocation` orders
  by state before relying on it).
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

- Step 1: corps `card_corp_14` Aphrodite, `_15` Celestic, `_16` Manutech, `_17` Morning Star Inc.,
  `_18` Viron in `misc/venus_material.csv` with cost, tags, `r` for production (Aphrodite `pp`, Manutech
  `ps`), Celestic `holds` Floater and vp `resCard/3`.
- Step 2: `raise_v` event, fired once per Venus step by any player **and by WGT** (step 5).
- Step 4: Morning Star `r` = `2pdeltav`.
- Steps 8A-8C: all project cards have rules; `ores(Floater)` etc. work.

## Rules

| Corp | Start | Rules |
|------|-------|-------|
| Aphrodite | 47 M€, 1 plant production | Effect: whenever Venus is raised 1 step (by anyone, including World Government), gain 2 M€. |
| Celestic | 42 M€ | First action: reveal cards from the deck until 2 cards **with a floater icon** are revealed; take them, discard the rest. Action: add 1 floater to ANY card. 1 VP per 3 floaters on this card. |
| Manutech | 35 M€, 1 steel production | Effect: for each step you increase the production of a resource, including this, also gain 1 of that resource (so it starts with 1 steel). Decreases give nothing. |
| Morning Star Inc. | 50 M€ | First action: reveal until 3 **Venus-tag** cards, take them, discard the rest. Venus requirements +/- 2 (done in step 4). |
| Viron | 48 M€ | Action: use a blue card action that has already been used this generation. |

## Tasks

1. **Aphrodite**: `e` = `raise_v:2m:this:any`. Confirm it fires once per step (2 steps = 4 M€).
2. **Manutech**: in `effect_incProduction` (~line 2277), if `$inc > 0` and the owner has `card_corp_16` on
   their tableau (`playerHasCard`), also `effect_incCount($color, <resource>, $inc)` (production tracker
   `pX` -> resource `X`). Make sure the corp's own starting `ps` triggers it (corp must be on the tableau
   before `r` runs - check `effect_playCorporation`). M€ production below 0 is not an "increase" unless the
   delta is positive.
3. **revealuntil op**: new `Operation_revealuntil` with params (condition, count), e.g.
   `revealuntil(tagVenus,3)` and `revealuntil(floater,2)`. Reveal from `deck_main` (reshuffle discard if
   empty, as normal draws do) until `count` matches; matches go to hand privately, the rest to
   `discard_main`; log the revealed cards. Define "floater icon" as: `holds == Floater`, or `Floater`
   appears in the card's `r`/`a`/`e` (e.g. Air-Scrapping Expedition, Atmoscoop, Colonies floater cards).
   Set Celestic `'a1'=>'revealuntil(floater,2)'` and Morning Star `'a1'=>'revealuntil(tagVenus,3)'`.
4. **Celestic action**: `a` = `ores(Floater)`.
5. **Viron**: new op `reuse` (`a` = `reuse`): choices are your tableau cards (including your corp) whose
   state is `MA_CARD_STATE_ACTION_USED`, that have an `a`, and that are not Viron itself; on choice push
   that card's `a` (copy `Operation_activate::effect` without changing state). Void when nothing qualifies.
   Check `isVoidSingle` for the chosen action's cost like `Operation_activate::argPrimaryDetails`.
6. `npm run build:material`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testAphroditeGainsOnAnyVenusRaise` - BCOLOR raises Venus 2 steps: Aphrodite owner (PCOLOR) +4 M€.
- `testAphroditeGainsOnWgtRaise` - WGT raises Venus: +2 M€.
- `testManutechStartingProduction` - after playing Manutech: steel production 1, steel 1.
- `testManutechGainsOnProductionIncrease` - `2pe`: energy +2. `pm`: +1 M€.
- `testManutechNoGainOnDecrease` - `npe`: energy unchanged.
- `testMorningStarRevealUntil3Venus` - stacked deck (non-Venus, Venus, non, Venus, Venus, ...): 3 Venus
  cards in hand, 2 non-Venus in discard, rest of deck untouched.
- `testCelesticRevealUntil2Floater` - counts a card with `holds Floater` and a card that only adds floaters.
- `testCelesticActionAndVp` - action adds a floater; 6 floaters -> 2 VP.
- `testVironReusesUsedAction` - use Development Center (or any blue action), then Viron: the action runs again.
- `testVironVoidWithoutUsedActions`.
- `testVironCannotReuseItself`.
- `testVenusCorpsDealtOnlyWithVenus` - with Venus off, none of `card_corp_14..18` exist.

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): Venus Next corporations`.

## Out of scope

Client presentation of revealed cards (step 10 can improve it).
