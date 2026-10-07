# Venus Next - Step 8B: project cards with floaters, microbes, animals and asteroids

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md` and `DESIGN.md` first.
- All game logic is in `modules/PGameXBody.php`. Game data lives in `misc/*.csv` (`|`-separated,
  `#set key=value` sets defaults for following rows). `npm run build:material` regenerates the
  `/* --- gen php begin <csvname> --- */` sections of `material.inc.php`; never hand-edit those.
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|text|text_action|text_effect|text_vp|php`.
  `r` immediate op expression, `a` action (blue cards), `e` triggered effect, `php` `'holds' => 'Floater'`
  = resource type kept on the card, `vp` `resCard/2` = 1 VP per 2 resources on the card.
- Op expressions: syntax at the top of `modules/OpExpression.php` (`/` or, `+` unordered and, `,` ordered
  and, `:` pay:get, `?` optional, prefix number = count). Resource-on-card ops:
  - `res` add to **this** card, `nres` remove from this card (`2nres:v` = pay 2 to raise Venus).
  - `ores(Type[,Tag])` add to one of **your** cards that holds `Type` (optionally only cards with tag
    `Tag`) - `Operation_ores.php`. It does not exclude the source card; check how "ANOTHER card" is
    handled elsewhere before relying on it.
  - `nores(Type)` remove from any card (e.g. Predators `nores(Animal):res`).
  - `nm`/`nmu`/`nms` pay M€ (with titanium / steel allowed), e.g. Water Import From Europa `12nmu:w`.
  - `counter(expr[,min[,max]])` sets the count of the next op: Titan Shuttles
    `(2ores(Floater,Jovian))/(counter(resCard,1):(nres,u))` = spend any number of floaters for that many
    titanium; Jupiter Floating Station `counter(resCard,null,4):m`.
  Examples: Regolith Eaters #33 `res/(2nres:o)`, Titan Air-Scrapping C43 `(nu:2res)/(2nres:tr)`,
  Jovian Lanterns C18 `2ores(Floater),tr` / `nu:2res`, Air Raid C02 `nres(Floater):5steal_m`.
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `mtFind("name", ...)`,
  `effect_playCard`, `st_gameDispatch`, `fakeUserAction($op, $target)`, `machine->getTopOperations`,
  `tokens->countTokensInLocation($card_id)` (resources on a card), `evaluateExpression("resFloater", $c)`.
  Resource tests to copy: `test_res` in `modules/tests/GameTest.php` (~line 1461).
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

- Steps 1-4, 7, 8A: Venus cards with data, requirements, `v` op, `tagVenus`, Venus cities; Maxwell Base
  and Stratopolis already have their `r` (city placement); `testAllVenusCardsParse` exists.
- Floaters already exist from the Colonies expansion (`'holds' => 'Floater'`, term `resFloater`).
- Source of truth for card text: `misc/venus_next_cards.json`.

## Rules notes

- "ANY card" = any of **your** cards that can hold that resource, including this one. "ANOTHER card" =
  excluding this one. "ANY Venus card" = your card with a Venus tag that holds that resource.
- "Add 1 resource to a Venus card" (Corroder Suits, Maxwell Base) = any resource type the target holds.
- You may add resources to cards only if they can hold them; if no target exists the add is skipped.

## Cards in this batch

| # | Card | Field | Proposed rules |
|---|------|-------|----------------|
| 213 | Aerial Mappers | a | `ores(Floater)/(nres:draw)` |
| 214 | Aerosport Tournament | r | `counter(all_city) m` - verify the "all cities in play" term name (`all_city`, used in Martian Zoo `pre`) |
| 215 | Air-Scrapping Expedition | r | `v,3ores(Floater,Venus)` |
| 217 | Atmoscoop | r | `(2t/2v),2ores(Floater)` |
| 219 | Corroder Suits | r | `2pm,ores(<any>,Venus)` - needs "any resource" form, see task 2 |
| 221 | Deuterium Export | a | `res/(nres:pe)` |
| 223 | Extractor Balloons | r, a | r `3res`; a `res/(2nres:v)` |
| 224 | Extremophiles | a | `ores(Microbe)`; vp `resCard/3` |
| 225 | Floating Habs | a | `2nm:ores(Floater)`; vp `resCard/2` |
| 226 | Forced Precipitation | a | `(2nm:res)/(2nres:v)` |
| 227 | Freyja Biodomes | r | `(2ores(Microbe,Venus)/2ores(Animal,Venus)),npe,2pm` (ANOTHER Venus card) |
| 231 | Hydrogen to Venus | r | `v,counter(tagJovian) ores(Floater,Venus)` - all floaters to one card |
| 234 | Jet Stream Microscrappers | a | `(nu:2res)/(2nres:v)` |
| 235 | Local Shading | a | `res/(nres:pm)` |
| 238 | Maxwell Base | a | `ores(<any>,Venus)` (ANOTHER Venus card) |
| 243 | Rotator Impacts | a | `(6nmu:res)/(nres:v)`; holds `Asteroid` |
| 248 | Stratopolis | a | `2ores(Floater,Venus)`; vp `resCard/3` |
| 249 | Stratospheric Birds | r, a | r: spend 1 floater from any of your cards (`nores(Floater)`? it must be **your** card and mandatory - check `Operation_nores` and Air Raid's `nres(Floater)`); a `res`; vp `resCard` |
| 251 | Sulphur-Eating Bacteria | a | `res/(counter(resCard,1):(nres,3m))` |
| 253 | Thermophiles | a | `ores(Microbe,Venus)/(2nres:v)` |
| 257 | Venus Soils | r | `v,pp,2ores(Microbe)` (ANOTHER card) |
| 260 | Venusian Insects | a | `res`; vp `resCard/2` |
| 261 | Venusian Plants | r | `v,(ores(Microbe,Venus)/ores(Animal,Venus))` (ANOTHER Venus card) |

## Tasks

1. Fill the rules above in `misc/venus_material.csv`; check `holds` and `vp` against the JSON.
2. **"Any resource" target.** `Operation_ores::argPrimaryDetails` keeps cards whose `holds` equals param 0,
   or any holding card when param 0 is empty. Find a syntax that passes an empty first param with a tag
   second param (e.g. `ores('',Venus)`), or add a small explicit form; when the target holds e.g. Animal,
   the added resource must be of that type. Make sure the resource-type naming in logs works.
3. **"ANOTHER card".** If `ores` does not exclude the source card, add that (e.g. a third param or a
   separate op) and use it for Freyja Biodomes, Maxwell Base, Venus Soils, Venusian Plants. Do not change
   existing Colonies cards' behaviour unless their text says ANOTHER (check `C11 Floater Prototypes`,
   `C12 Floater Technology` - if they are wrong today, fix them and note it in the commit).
4. **Stratospheric Birds** requirement "spend 1 floater from any card" must make the card unplayable when
   you have no floaters (`postcondition` checks the first rule's `isVoid`).
5. `npm run build:material`; `testAllVenusCardsParse` must stay green.

## Tests (append to `modules/tests/VenusTest.php`)

- `testAirScrappingExpeditionOnlyVenusTargets` - you own a Jovian floater card (e.g. Jupiter Floating
  Station, Colonies on) and a Venus floater card (Dirigibles): only the Venus card is offered.
- `testAtmoscoopChoiceTempOrVenus` - both options offered; choosing Venus gives +4%, 2 floaters added.
- `testExtractorBalloonsSpend2Floaters` - starts with 3 floaters; action "remove 2" raises Venus.
- `testJetStreamTitaniumForFloaters` - 1 titanium -> 2 floaters.
- `testRotatorImpactsPayWithTitanium` - 6 M€ payable with 2 titanium; spending the asteroid raises Venus.
- `testStratosphericBirdsRequiresFloater` - unplayable with no floaters; playable with one, which is
  removed on play.
- `testSulphurEatingBacteriaTripleMC` - 3 microbes, spend 3: +9 M€, 0 microbes left.
- `testCorroderSuitsAnyResourceVenusCard` - target is a Venus animal card (Stratospheric Birds): an
  animal is added.
- `testMaxwellBaseNotItself` - Maxwell Base's action does not offer Maxwell Base.
- `testHydrogenToVenusPerJovian` - 2 Jovian tags: 2 floaters on the chosen Venus card.
- `testFloatingHabsVp` - 5 floaters -> 2 VP; `testStratopolisVp` - 7 floaters -> 2 VP.
- `testVenusFloaterCardWithColoniesFloaterTarget` - Colonies and Venus on: Aerial Mappers' "add a floater to
  ANY card" can target Titan Air-scrapping.
- `testVenusSoilsAnotherCard` - Venus +2%, plant production +1, 2 microbes on a different microbe card.

## Done when

- All tests pass, `npm run lint:php` clean.
- Commit: `feat(venus): rules for Venus resource cards`.

## Out of scope

Dirigibles, Comet for Venus, Io Sulphur Research, Sponsored Academies, Venus Magnetizer, Venus Waystation,
Venusian Animals (8C). Corporations (9).
