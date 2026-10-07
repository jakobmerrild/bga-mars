# Venus Next - Step 7: off-Mars Venus city areas

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md` and `DESIGN.md` first.
- All game logic is in `modules/PGameXBody.php`. Game data lives in `misc/*.csv` (`|`-separated,
  `#set key=value` sets defaults for following rows). `npm run build:material` regenerates the
  `/* --- gen php begin <csvname> --- */` sections of `material.inc.php`; never hand-edit those.
- Card CSV columns: `num|name|t|r|a|e|cost|pre|tags|vp|deck|...`; `r` = immediate op expression.
- Maps: 0 Tharsis, 1 Elysium, 2 Hellas, 3 Vastitas Borealis, 4 Amazonis Planitia.
- Client: `src/*.ts` compiles to `terraformingmars.js` (`npm run build:ts`), `src/css/*.scss` to
  `terraformingmars.css` (`npm run build:scss`); static layout in `terraformingmars_terraformingmars.tpl`.
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Existing tile tests to copy: `testNoctisCity`,
  `testResolveOcean` in `modules/tests/GameTest.php` (~lines 671, 808).
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available (from step 1)

`isVenusVariant()`, `GameUT::init($map, $colonies, $venus)`, Venus cards (data only) in
`misc/venus_material.csv`, `modules/tests/VenusTest.php`.

## Rules decisions you need

- Venus Next adds four off-Mars city areas (on the Venus board), each reserved for one card: **Dawn City**
  (#220), **Luna Metropolis** (#236), **Maxwell Base** (#238), **Stratopolis** (#248).
- A city placed there **counts as a city tile in play** (Mayor, "cities in play" counts, Rover
  Construction, Pets, Immigrant City, Tharsis Republic's "when you place a city: 3 M€") but **not as a city
  on Mars** (Zeppelins, Martian Rails, Tharsis Republic's "city on Mars: +1 M€ production"). No placement
  bonus, no adjacency.

## Background

Existing off-Mars areas: every map section in `misc/map_material.csv` (header `x|y|r|name|ocean|php`)
starts with `#set reserved=1`, `#set inspace=1` rows `0|1||Stanford Torus`, `0|2||Phobos Space Haven`,
`0|3||Ganymede Colony` (sections at ~lines 10, 82, 164, 245, 330 - one per map, ids `hex_{x}_{y}@mN`).
Cards target them by name: Phobos Space Haven `r` = `pu,city('Phobos Space Haven')`, Ganymede Colony
`city('Ganymede Colony')`. `AbsOperationTile` (`getReservedArea()` ~line 71, checks ~line 161-190) matches
the op parameter against the hex name. `AbsOperationTile::effect_placeTile()` (~line 201) increments
`city` always and `cityonmars` + `place_cityonmars` only when the hex is not `inspace`.
Client: the space hexes are static divs in `terraformingmars_terraformingmars.tpl` (~line 30, `hex_0_1`,
`hex_0_2` in `.hex_phobos`, ...) positioned in `src/css/Map.scss` (~lines 358-395) and
`src/css/VLayout.scss` (~lines 469-515).

## Tasks

1. In **each** of the 5 map sections of `misc/map_material.csv`, in the `inspace` block, add
   `0|4||Dawn City`, `0|5||Luna Metropolis`, `0|6||Maxwell Base`, `0|7||Stratopolis` (check first that
   `hex_0_4`..`hex_0_7` are unused on every map).
2. Create these hexes only with Venus on (gate in `createTokens()` / map building - check how hexes are
   created and whether `getNonReservedHexes()` / solo setup could ever pick them; they are `reserved`, so
   they should already be excluded).
3. Make sure nothing else can be placed there: only the card op naming the area may target it (same as
   Phobos). Verify Stanford Torus-style "any space area" logic (if any) does not accept them.
4. Set the `r` rules of the four cards in `misc/venus_material.csv` (rules from
   `misc/venus_next_cards.json`):
   - Dawn City 220: `npe,pu,city('Dawn City')` (requires 4 science tags - `pre` already set in step 1).
   - Luna Metropolis 236: `counter(tagEarth) pm,city('Luna Metropolis')` - same pattern as Cartel #137
     (`counter(tagEarth) pm`, "including this": the card's own tags are already counted when `r` runs).
     Verify the combined expression parses (`OpExpression::arr`).
   - Maxwell Base 238: `npe,city('Maxwell Base')` (its action is step 8B).
   - Stratopolis 248: `2pm,city('Stratopolis')` (its action is step 8B).
   Check how energy-production decreases are written (`npe`) and that `r` is checked for affordability.
5. Client minimum: add four hidden-unless-Venus divs `hex_0_4`..`hex_0_7` to the tpl next to the other space
   hexes so tile placement has a target element, with simple placeholder positions in `Map.scss` and
   `VLayout.scss` (a row next to Ganymede is fine). Show them only with Venus on (e.g. a body class - check
   how `exp-colonies` is added in `src/GameXBody.ts` ~line 197 and add `exp-venus` the same way).
   Visual polish is step 10.
6. `npm run build:material`, `npm run build:ts`, `npm run build:scss`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testVenusCityHexesOnlyWithVenus` - for maps 0-4: the four hexes exist with Venus on and not with it off.
- `testDawnCityPlacesOnReservedArea` - Dawn City's city op offers exactly one target, the Dawn City hex.
- `testVenusCityHexNotOfferedToNormalCity` - the standard-project city op does not offer any of the four.
- `testVenusCityCountsAsCityNotOnMars` - after placing Stratopolis: `city` +1, `cityonmars` +0.
- `testTharsisRepublicVenusCity` - Tharsis Republic (`card_corp_11`) owner places Luna Metropolis: +3 M€,
  M€ production unchanged.
- `testOnMarsCountersIgnoreVenusCities` - one Mars city + Stratopolis in play: Zeppelins / Martian Rails
  count 1 (`cityonmars` / `all_cityonmars` terms), Greenhouses / Energy Saving count 2 (all cities in play).

## Done when

- All tests pass, `npm run lint:php` clean, client builds.
- Commit: `feat(venus): off-Mars Venus city areas`.

## Out of scope

Card art and final Venus board graphics (step 10). Maxwell Base / Stratopolis actions (step 8B).
