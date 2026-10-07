# Venus Next - Step 1: game option, variant plumbing, Venus material file

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md` and `DESIGN.md` first.
- Server class chain: `terraformingmars.game.php` -> `PGameXBody` (all game logic, `modules/PGameXBody.php`)
  -> `PGameMachine` -> `PGameTokens` -> `PGameBasic` -> `Table`.
- Game data lives in `misc/*.csv` (`|`-separated, `#set key=value` sets defaults for the following rows).
  `npm run build:material` regenerates the parts of `material.inc.php` between
  `/* --- gen php begin <csvname> --- */` and `/* --- gen php end <csvname> --- */`. Never hand-edit those
  sections. A **new** CSV needs its two marker lines added to `material.inc.php` by hand first (genmat fails
  with "missing markup" otherwise). Everything outside the markers is hand-written.
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|text|text_action|text_effect|text_vp|php`.
  `t`: 1 automated, 2 active (blue), 3 event, 4 corporation. `r` immediate op expression, `a` action,
  `e` triggered effect, `pre` requirement (MathExpression, e.g. `tagScience>=3`, `o<=6`), `vp` number or
  expression (`resCard/2`). Tags are space-separated (`Space Jovian`); the power tag is spelled `Energy`
  and events add `Event` last. Corps: `cost` is the **negative** starting M€ (`-45`),
  `r` holds starting production/resources (`pp`, `5s`), `php` `'a1'=>'...'` is the first action,
  `'holds'=>'Floater'` the resource kept on the card.
- Operation types map to `modules/operations/Operation_<type>.php` unless `misc/op_material.csv` sets a
  `class`. Lookup: `PGameXBody::getOperationInstance`.
- Tokens: per-player trackers `tracker_<x>_<color>`, global params `tracker_t`, `tracker_o`, `tracker_w`
  (type `param`, location `params`), cards `card_main_<num>`, corps `card_corp_<num>`.
- Tests: `npm run test` (PHPUnit, ~2s), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php` (in-memory tokens and machine, players `PCOLOR` and `BCOLOR`). Look at
  `modules/tests/GameTest.php` for style, e.g. `testColony` / `testTrade` (~line 1796).
- Before committing: `npm run test` and `npm run lint:php` (ideally `npm run predeploy`). End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.
- The shell is Git Bash on Windows; `npm run` scripts assume `php8.4` and `phpunit` are on PATH.

## Rules decisions you need

- Venus Next adds a deck `Venus`: 49 project cards (#213-#261) and 5 corporations (Aphrodite, Celestic,
  Manutech, Morning Star Inc., Viron). It is an option; with it off nothing Venus-related may exist.

## Goal

Add the game option and make Venus cards and corps exist (as data only) exactly when the option is on.
Later steps add rules to the cards.

## Tasks

1. **Option.** In `misc/other/gameoptions.json` and `gameoptions.json` add option `111`:
   `"name": "Expansion: Venus Next"`, `"$varname": "venus"`, values `1` On (description, `"tmdisplay":
   "Venus Next"`, `"nobeginner": true`, `"alpha": true`) and `0` Off, `"default": 0`. Copy the shape of
   option `108` (Colonies). Run `node misc/other/genoptions.js`: it prints missing `define(...)` lines;
   add them to the hand-written defines in `material.inc.php` next to `MA_OPT_COLONIES` (~line 117), and
   it prints the missing `"var_venus" => 111` line for the option map in `modules/PGameXBody.php` (~line 48).
2. **Variant helpers** in `modules/PGameXBody.php` next to `isColoniesVariant()` (~line 344):
   `isVenusVariant()` returning `$this->getGameStateValue("var_venus") == 1`, and
   `debug_optionVenus(int $number)` next to `debug_optionColonies` (~line 830).
3. **Deck filtering.** In `createTokens()` (~line 816) skip `card_*` tokens with `deck == "Venus"` when
   Venus is off (mirror the Colonies block). In `initTables()` log `Module: Venus Next` like Colonies does.
4. **Material file.** Create `misc/venus_material.csv` with the header of `misc/colo_material.csv`. Add
   `/* --- gen php begin venus_material --- */` and `/* --- gen php end venus_material --- */` to
   `material.inc.php` (next to the `colo_material` section). Sections in the CSV:
   - project cards: `#set id=card_main_{num}`, `#set location=deck_main`, `#set create=single`,
     `#set type=card main venus_main e{deck}`
   - corps: `#set id=card_corp_{num}`, `#set count=1`, `#set create=single`, `#set location=deck_corp`,
     `#set type=card corp venus_corp e{deck}` (copy the colo corp section, `misc/colo_material.csv` ~line 62).
5. **Card data** from `misc/venus_next_cards.json`. One row per card: `num`, `name`, `t` (automated 1,
   active 2, event 3), `cost`, `tags` (JSON tags joined by spaces - already in CSV spelling), `vp` (number,
   or `resCard/N` for `{amount:1, per:N}`; `resCard` for per 1), `deck` = `Venus`, `text`, `text_action`,
   `text_effect`, `text_vp`, and `php` `'holds' => '<Resource>'` when `holds` is set.
   - Leave `r`, `a`, `e` **empty** (steps 8-9 add rules).
   - `pre`: fill only tag requirements and `tr>=25` for Terraforming Contract. Single tag:
     `tagScience>=3`, `tagVenus>=2`. Several different tags must count wild tags the way Advanced
     Ecosystems (#135 in `misc/cards_material.csv`) does:
     `((((tagVenus>0) + (tagEarth>0)) + (tagJovian>0)) + tagWild) >= 3` (Luxury Foods, Mining Quota,
     Omnicourt, Solarnet) and `((tagVenus>0) + (tagEarth>0)) + tagWild >= 2` (Sister Planet Support).
     `tagVenus` only evaluates once step 3 enables its tracker, so these rows may fail to evaluate until
     then - that is expected; do not test playability of them in this step. **Leave Venus % requirements and the floater requirement empty**
     (`tracker_v` does not exist yet; step 4 fills them).
6. **Corporations.** `misc/cards_material.csv` has them commented out (`#14`-`#18`, ~line 302). Delete those
   commented rows and add the corps to `venus_material.csv` using the **active** corp convention:
   Aphrodite `r`=`pp`, cost `-47`; Celestic cost `-42`, `'holds'=>'Floater'`, vp `resCard/3`; Manutech
   `r`=`ps`, cost `-35`; Morning Star Inc. cost `-50`; Viron cost `-48`. Tags from the JSON. Keep all
   text columns. No `a`/`e` rules yet.
7. Run `npm run build:material` and check the new section was generated.
8. **Test harness.** In `modules/tests/GameUT.php` add `var $var_venus = 0;`, extend
   `init(int $map = 0, int $colonies = 0, int $venus = 0)` to store it, and override `isVenusVariant()`
   (like `isColoniesVariant()`).

## Tests (create `modules/tests/VenusTest.php`)

Use `final class VenusTest extends TestCase` like `GameTest.php`.

- `testVenusOptionOffCreatesNoVenusTokens` - `(new GameUT())->init(0, 0, 0)`: no token whose rules have
  `deck == "Venus"` exists in `$m->tokens`.
- `testVenusOptionOnCreatesVenusTokens` - `init(0, 0, 1)`: all 49 `card_main_213..261` are in `deck_main`
  and `card_corp_14..18` are in `deck_corp`.
- `testVenusMaterialMatchesJson` - for each JSON card, `getRulesFor(card_main_<num>, "cost")` and `name`
  match `misc/venus_next_cards.json` (guards against transcription errors).
- Run the whole suite: existing tests must stay green (e.g. `testProductionBuildCards` counts building
  cards per deck and must not change for Basic/Corporate/Prelude).

## Done when

- `npm run test` and `npm run lint:php` pass; `material.inc.php` regenerated, no manual edits inside markers.
- Commit: `feat(venus): add Venus Next option and card data`.

## Out of scope

Card rules, the Venus track, any client changes.
