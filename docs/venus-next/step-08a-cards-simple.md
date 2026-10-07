# Venus Next - Step 8A: project cards that only need existing operations

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
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|text|text_action|text_effect|text_vp|php`.
  `r` immediate op expression, `a` action, `e` triggered effect.
- Op expressions: syntax at the top of `modules/OpExpression.php` (`/` or, `+` unordered and, `,` ordered
  and, `:` pay:get, `?` optional, prefix number = count). Op types map to `modules/operations/Operation_<type>.php`
  or a class in `misc/op_material.csv`. Common ops: `m s u p e h` gain resource, `pm ps pu pp pe ph` raise
  production, `npm npe ...` lower own production, `t` temperature, `o` oxygen, `w` ocean, `v` Venus,
  `tr` TR, `draw`, `city`, `forest`. Examples from `misc/cards_material.csv`:
  - Release of Inert Gases #36: `2tr`; Research #90: `2draw`
  - Comet #10: `t+w+3np_Any`
  - Cartel #137: `counter(tagEarth) pm` (counts include the card's own tags - "including this")
  - Toll Station #99: `counter(opp_tagSpace) pm`
  - Phobos Space Haven #21: `pu,city('Phobos Space Haven')`
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `mtFind("name", ...)`,
  `effect_playCard($color, $card_id)`, `st_gameDispatch()`, `fakeUserAction($op, $target)`,
  `machine->getTopOperations($color)`, `getTrackerValue`, `tokens->setTokenState`, `playability`.
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

- Step 1: Venus cards `card_main_213..261` in `misc/venus_material.csv` with name, type, cost, tags, vp,
  text and tag requirements. `isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, `VenusTest.php`.
- Step 2: op `v` (raise Venus 1 step, `2v` = 2 steps), `tracker_v`.
- Step 3: `tagVenus` counting. Step 4: Venus % requirements in `pre`.
- Step 7: Dawn City (#220) and Luna Metropolis (#236) are already done.
- Source of truth for card text: `misc/venus_next_cards.json`.

## Cards in this batch (fill `r` in `misc/venus_material.csv`)

| # | Card | Proposed `r` | Notes |
|---|------|--------------|-------|
| 216 | Atalanta Planitia Lab | `2draw` | |
| 228 | GHG Import From Venus | `v,3ph` | event |
| 229 | Giant Solar Shade | `3v` | |
| 230 | Gyropolis | `2npe,counter(tagVenus+tagEarth) pm,city` | Gyropolis has no Venus/Earth tag itself; check `counter` accepts a sum (see `counter('(tagScience+1)/3')` in `GameTest::testEvaluteCounter`) |
| 233 | Ishtar Mining | `pu` | |
| 237 | Luxury Foods | (none) | only VP 2 and the requirement |
| 239 | Mining Quota | `2ps` | |
| 240 | Neutralizer Factory | `v` | |
| 241 | Omnicourt | `2tr` | |
| 242 | Orbital Reflectors | `2v,2ph` | |
| 244 | Sister Planet Support | `3pm` | |
| 245 | Solarnet | `2draw` | |
| 246 | Spin-Inducing Asteroid | `2v` | event |
| 250 | Sulphur Exports | `v,counter(tagVenus) pm` | "including this" |
| 252 | Terraforming Contract | `4pm` | requirement `tr>=25` already set |
| 254 | Water to Venus | `v` | event |
| 255 | Venus Governor | `2pm` | |

Check each against the card text in the JSON (production decreases must be affordable: `postcondition`
checks the first rule). Check VP columns: Atalanta 2, Luxury Foods 2, Solarnet 1, Terraforming Contract 0.

## Tasks

1. Fill `r` for the table above; `npm run build:material`.
2. Add the card-wide sanity tests below (they cover all 49 cards, including ones still without rules).

## Tests (append to `modules/tests/VenusTest.php`)

- `testAllVenusCardsParse` - for every token type with `deck == "Venus"`: each non-empty `r`/`a`/`e`
  parses (`OpExpression::arr`) and each leaf op type resolves via `getOperationInstanceFromType`.
  Keep this test for 8B/8C - it catches typos across all cards.
- `testAllVenusCardsHaveValidPre` - every non-empty `pre` evaluates without exception (Venus on).
- `testGiantSolarShade` - Venus +6%, TR +3.
- `testGHGImportFromVenus` - Venus +2%, heat production +3, TR +1.
- `testSulphurExportsCountsItself` - with 1 other Venus tag in play: M€ production +2.
- `testGyropolisVenusAndEarth` - 2 Venus + 1 Earth tags, energy production 2: M€ production +3, energy
  production 0, a city placed. Unplayable with energy production 1.
- `testTerraformingContractNeeds25TR` - unplayable at TR 24, playable at 25.
- `testLuxuryFoodsRequirementWithWild` - Venus + Earth + Wild tag: playable; Venus + Earth only: not.
- `testSpinInducingAsteroidMaxReq` - playable at Venus 10%, not at 12%.
- `testNeutralizerFactoryAtVenusBonus` - Venus 6% -> card needs 10%: unplayable; at 14% play it: 16%
  reached, TR +2 (step + bonus).

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): rules for simple Venus project cards`.

## Out of scope

Cards with resources on them (8B), special rules (8C), corporations (9).
