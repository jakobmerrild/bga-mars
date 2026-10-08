# Venus Next - alpha implementation plan

Goal: a playable **alpha** of Venus Next behind a new game option. "Alpha" means rules complete for
the 49 project cards, 5 corporations, Venus track, World Government Terraforming (WGT), Air Scrapping,
Hoverlord and Venuphile - with functional (not polished) client UI.

Source data: [misc/venus_next_cards.json](misc/venus_next_cards.json) (cards #213-#261, corps #14-#18).

Per-step agent briefs (self-contained, more detailed than this file, and authoritative where they differ):
[docs/venus-next/README.md](docs/venus-next/README.md).

Each step below is meant to land as its own commit, ends with `npm run test` green, and lists the new
tests. New server tests go in `modules/tests/VenusTest.php` unless noted.

---

## Step 0 - Settle open rules questions (no code)

Decide these before Step 4/5; record answers at the top of `VenusTest.php` as comments.

| # | Question | Answer |
|---|----------|--------|
| Q1 | Do Adaptation Technology / Special Design / Inventrix modify **Venus** requirements? | **Decided: yes, all three.** The general delta (`pdelta`, `onPre_delta`) applies to t/o/w **and** v. Morning Star Inc. adds a Venus-only delta on top. |
| Q2 | Does WGT raising Venus trigger Aphrodite? | **Decided: yes.** |
| Q3 | Does WGT get track bonuses (O2 8% -> temp, temp -> ocean, Venus 8%/16%) or ocean placement bonuses? | **Decided:** no TR and no player bonuses (heat production, Venus 8%/16%, placement bonuses), but bonuses that raise another parameter (O2 8% -> temp, temp 0 -> ocean) still happen, without TR. |
| Q4 | Solo: must Venus be maxed to win? Does WGT run in solo? | **Decided (official solo rules): yes and yes.** With Venus Next, the standard solo goal is all four parameters maxed, Venus included. Still 14 TR and 14 generations. The solo player is always first player, so chooses every WGT step. WGT is skipped in the last generation because the Game End Check comes first. See Step 5. |
| Q5 | Does a Venus off-Mars city count for Mayor, Rover Construction, Pets, Tharsis Republic etc.? | **Decided: not on Mars.** Counts as a city tile in play, but not as a city on Mars (same as Phobos Space Haven today). |

---

## Step 1 - Game option and variant plumbing

**Changes**
- `misc/other/gameoptions.json` + `gameoptions.json`: option `111` "Expansion: Venus Next", `$varname`
  `venus`, values On/Off, `"alpha": true`, default 0. Run `node misc/other/genoptions.js` to print the
  `MA_OPT_VENUS` defines and add them to `material.inc.php` (hand-written part).
- `modules/PGameXBody.php`: `"var_venus" => 111` in the option map, `isVenusVariant()`,
  `debug_optionVenus(int)`. In `createTokens()` skip cards with `deck == "Venus"` when off (mirror the
  Colonies block). Add a "Module: Venus Next" log line in `initTables()`.
- `modules/tests/GameUT.php`: add `$var_venus`, `init(int $map = 0, int $colonies = 0, int $venus = 0)`,
  override `isVenusVariant()`.

**Tests**
- `testVenusOptionOffCreatesNoVenusTokens` - with `init(0,0,0)` no `card_*` with deck `Venus`, no `tracker_v`.
- `testVenusOptionOnCreatesVenusTokens` - with `init(0,0,1)` all 49 project cards are in `deck_main`,
  the 5 corps in `deck_corp`.

---

## Step 2 - Venus global parameter and Air Scrapping

**Changes**
- `misc/tracker_material.csv`, terraforming parameters block: `tracker_v|Venus|0|'max'=>30`.
  Created only for the Venus variant (gate in `createTokens()` like `card_stanproj_8`).
- Track bonuses (same mechanism as `param_t_0`, `param_o_8`, consumed by `effect_increaseParam`):
  `param_v_8` -> `draw`, `param_v_16` -> `tr`.
- `misc/op_material.csv`: `v|Raise Venus`. New `modules/operations/Operation_v.php`, a copy of
  `Operation_t` with step size 2 and its own "already at maximum" prompt.
- `effect_increaseParam`: after the change, fire a trigger event `raise_v` (owner = raising player,
  listeners with `:any` see it). Needed for Aphrodite in Step 9.
- **Keep Venus out of** `getTerraformingProgression()` / `isEndOfGameAchived()` (the game still ends on
  Mars only). Optionally expose `getVenusProgression()` for the progress bar.
- `evaluateTerm`, `param` branch: `v` uses step multiplier 2 (like `t`) when applying requirement mods.
- Standard project `9|Air Scrapping|0|v|15|...` (`proj_material.csv` or a new Venus CSV, see Step 8);
  create only when Venus is on.

**Tests**
- `testRaiseVenusIncreasesTrackAndTR` - push `v`, dispatch: `tracker_v` 0 -> 2, TR +1.
- `testRaiseVenus2Steps` - `2v` -> 4%, TR +2.
- `testVenusBonusAt8DrawsCard` - start at 6, raise 1: hand +1.
- `testVenusBonusAt16GivesTR` - start at 14, raise 1: TR +2 (step + bonus).
- `testVenusCapsAt30` - at 28, `2v` -> 30, TR +1 only, op prompts confirmation when already 30.
- `testVenusDoesNotAffectEndOfGame` - max t/o/w with Venus 0: `isEndOfGameAchived()` true; max only
  Venus: false.
- `testAirScrappingOnlyWithVenus` - `card_stanproj_9` exists with Venus on, absent with it off;
  playing it costs 15 M€ and raises Venus.

---

## Step 3 - Venus tag

**Changes**
- `misc/tracker_material.csv`: un-comment `tracker_tagVenus` (tag counting is generic once the tracker
  exists). Check that `uniquetags`, the Generalist/Specialist counts and Aridor's `play_newtag` include Venus.

**Tests**
- `testPlayVenusCardCountsTag` - play Ishtar Mining: `tracker_tagVenus` = 1.
- `testVenusGovernorCountsTwoTags` - Venus Governor adds 2.
- `testVenusTagRequirement` - Sister Planet Support unplayable without Venus+Earth tags, playable with.
- `testVenusTagTriggersPlayTagVenus` - a dummy listener on `play_tagVenus` fires.

---

## Step 4 - Venus requirements and modifiers

**Changes**
- Requirements in CSV `pre` use the param term: `v>=10`, max reqs `v<=14`.
- Per Q1, the existing general delta already covers Venus: `tracker_pdelta` (Adaptation Technology,
  Inventrix) and `onPre_delta` listeners (Special Design) apply to `v` like any other param. The
  `param` branch of `evaluateTerm` already applies `mods` to every param, so `v` only needs the x2 step
  multiplier from Step 2. Check that Inventrix actually sets `pdelta` (its corp rules).
- Morning Star Inc. needs a **Venus-only** extra: new per-player tracker `tracker_pdeltav` and op
  `pdeltav` (class `Operation_R`, like `pdelta`). In `evaluatePrecondition` pass
  `["mods" => d, "vmods" => dv]`. In `evaluateTerm`, `v` gets `mods + vmods` and `t/o/w` get only
  `mods`. Both deltas are tried with the same sign, matching the current "+delta or -delta" check.

**Tests**
- `testVenusMinRequirement` - Neutralizer Factory (`v>=10`) fails at 8, passes at 10.
- `testVenusMaxRequirement` - Rotator Impacts (`v<=14`) passes at 14, fails at 16.
- `testVenusDeltaAppliesOnlyToVenus` - with `pdeltav=2`, a `v>=10` card passes at 6, but a `t>=-12`
  card does not get the extra 2 steps.
- `testVenusDeltaWorksForMaxReq` - with `pdeltav=2`, Spin-Inducing Asteroid (`v<=10`) passes at 14.
- `testAdaptationTechnologyAppliesToVenus` - with `pdelta=2`, `v>=10` passes at 6 and `v<=14`
  passes at 18.
- `testSpecialDesignAppliesToVenus` - Special Design in play: next Venus-requirement card passes 2
  steps early, then Special Design flips.
- `testInventrixAppliesToVenus` - Inventrix owner plays `v>=10` at 6.
- `testMorningStarStacksWithAdaptationTechnology` - both: `v>=10` passes at 2 (4 steps).

---

## Step 5 - Solar phase: World Government Terraforming

**Changes**
- `effect_endOfTurn()` already has the placeholder `// step 2: world goverment: venus only`. If
  `isVenusVariant()` and the game is not ending, queue `wgt` for the **first player of the generation
  that just ended** (`getCurrentStartingPlayer()`, before it is advanced).
- New `Operation_wgt`: choices are the non-maxed params among `t`, `o`, `w`, `v`. It resolves through
  a "no TR, no bonuses" path. Add an option to `effect_increaseParam` (e.g. `["wgt" => true]`) that
  skips `effect_incTerraformingRank` and the bonus loop. Still fire `raise_v` (Q2).
- Ocean via WGT: `w` with a flag that suppresses placement bonuses and TR. Check `AbsOperationTile` /
  `Operation_w` for where bonuses are collected and add the guard. The first player picks the hex.
- Log message: "World Government raises ${token_name}".
- If every param is maxed, the op is void and auto-skips.
- **Solo (Q4).** No WGT changes are needed. The solo player is the starting player every generation,
  and `isEndOfGameAchived()` already returns true in the last solo generation, so the end check skips
  WGT there. The win condition does change: in `effect_finalScoring()`, the standard solo flavour
  (`var_solo_flavour == 0`) checks `getTerraformingProgression() >= 100`. With Venus on it must also
  require `tracker_v` at max. Add a helper, e.g. `isSoloTerraformingComplete()`, and update the
  "goal was..." log text to mention Venus. The TR63 flavour is unchanged. `getLastGeneration()` is
  unchanged (14, or 12 with Prelude).

**Tests**
- `testWgtQueuedOnlyWithVenus` - end of generation without Venus: no `wgt` op; with Venus: one `wgt`
  op for the starting player.
- `testWgtRaisesVenusWithoutTR` - resolve `wgt` -> `v`: Venus +2, TR unchanged.
- `testWgtTemperatureNoBonus` - temperature at -26, WGT raise: no heat-production bonus at -24.
- `testWgtOceanNoPlacementBonus` - WGT ocean on a 2-plant hex: plants and TR unchanged.
- `testWgtSkipsMaxedParams` - Venus maxed: `v` not offered; all maxed: op void.
- `testWgtNotRunWhenGameEnds` - Mars terraformed after production: final scoring queued, no `wgt`.
- `testWgtRunsBeforeColonyProduction` - with Venus and Colonies both on, order is WGT, then colony
  track increase, then research.
- `testSoloWgtEachGenerationBeforeLast` - solo with Venus, end of generation 13: `wgt` queued for
  the solo player.
- `testSoloWgtSkippedInLastGeneration` - solo with Venus, end of generation 14: final scoring queued,
  no `wgt`.
- `testSoloVenusWinRequiresVenusMaxed` - Mars maxed, Venus at 28: solo loss. Venus at 30: win.
- `testSoloWithoutVenusWinUnchanged` - Venus off, Mars maxed: win (no regression).
- `testSoloTR63IgnoresVenus` - TR63 flavour with Venus on: TR 63 wins even with Venus at 0.

---

## Step 6 - Hoverlord milestone and Venuphile award

**Changes**
- With Venus on, add one extra milestone and award to every map: `Hoverlord` (`resFloater>=7`, the term
  already exists) and `Venuphile` (`tagVenus`). Material is per map (`milestone_{num}@mN`), so define
  them once with a map-independent id (e.g. `milestone_6` / `award_6`, location `display_milestones`)
  and create them only when Venus is on. Fund/claim limits stay at 3.
- Client: the milestone/award row needs to fit 6 entries.

**Tests**
- `testHoverlordClaim` - 6 floaters: not claimable; 7 floaters across two cards: claimable.
- `testVenuphileAward` - player with 2 Venus tags beats 1 in the award ranking.
- `testVenusOptionOnAddsHoverlordAndVenuphile` - with `init($map,0,1)` the Hoverlord milestone is in
  `display_milestones` and Venuphile in `display_awards`, on every map (loop maps 0-4).
- `testVenusMilestonesAbsentWithoutVenus` - with `init($map,0,0)` neither is created, on every map.
- `testMaxThreeMilestonesStillEnforcedWithSix` - claim 3, the 4th is refused.

---

## Step 7 - Off-Mars Venus city areas

**Changes**
- `misc/map_material.csv`: for **every** map section add 4 `inspace=1`, `reserved=1` hexes named
  Dawn City, Luna Metropolis, Maxwell Base, Stratopolis, next to the existing Stanford Torus/Phobos/
  Ganymede rows. Create them only when Venus is on, or keep them inert since only these cards can target them.
- Their cards use the existing reserved-area city placement (see how Phobos Space Haven / Ganymede
  Colony target their hex) - reuse that op form.

**Tests**
- `testDawnCityPlacesOnReservedArea` - only one legal target, the Dawn City hex.
- `testVenusCityCountsAsCityNotOnMars` - `city` +1, `cityonmars` +0 (Q5).
- `testTharsisRepublicVenusCity` - Tharsis owner gets the 3 M€ "you place a city" bonus but not the
  M€ production (not on Mars).
- `testOnMarsCountersIgnoreVenusCities` - with one Mars city and Stratopolis in play, Zeppelins and
  Martian Rails count 1 city on Mars, and Greenhouses / Energy Saving ("city tiles in play") count 2.

---

## Step 8 - Project cards (#213-#261)

**Material**: new `misc/venus_material.csv`, header copied from `colo_material.csv`, `#set
type=card main venus_main e{deck}`, `deck=Venus`, ids `card_main_{num}`. Register a
`gen php begin/end venus_material` section in `material.inc.php` and confirm `npm run build:material`
fills it. Convert each JSON entry: `t` (1/2/3), `cost`, `tags`, `pre`, `vp` (`resCard/3` etc.),
`'holds' => 'Floater'`, text columns. Then write `r`/`a`/`e` op expressions.

Do it in three batches; each batch is one commit.

**Batch A - existing ops only** (~18 cards)
216 Atalanta Planitia Lab, 220 Dawn City, 228 GHG Import From Venus, 229 Giant Solar Shade,
230 Gyropolis, 233 Ishtar Mining, 236 Luna Metropolis, 237 Luxury Foods, 239 Mining Quota,
240 Neutralizer Factory, 241 Omnicourt, 242 Orbital Reflectors, 244 Sister Planet Support,
245 Solarnet, 246 Spin-Inducing Asteroid, 250 Sulphur Exports, 252 Terraforming Contract,
254 Water to Venus, 255 Venus Governor.

**Batch B - resources on cards** (`res`, `nres`, `ores(Floater)`, `ores(Floater,Venus)`) (~23 cards)
213 Aerial Mappers, 214 Aerosport Tournament, 215 Air-Scrapping Expedition, 217 Atmoscoop,
219 Corroder Suits, 221 Deuterium Export, 223 Extractor Balloons, 224 Extremophiles, 225 Floating
Habs, 226 Forced Precipitation, 227 Freyja Biodomes, 231 Hydrogen to Venus, 234 Jet Stream
Microscrappers, 235 Local Shading, 238 Maxwell Base, 243 Rotator Impacts, 248 Stratopolis,
249 Stratospheric Birds, 251 Sulphur-Eating Bacteria, 253 Thermophiles, 257 Venus Soils,
260 Venusian Insects, 261 Venusian Plants.
New op forms likely needed: "ANY Venus card" filter (exists for Jovian as `ores(Floater,Jovian)` -
generalise to any resource type + Venus tag, including "any resource" for Corroder Suits/Maxwell Base),
and "spend X resources for 3X M€" (Sulphur-Eating Bacteria).

**Batch C - special rules** (8 cards)
218 Comet for Venus (remove M€ only from a player with a Venus tag), 222 Dirigibles (floaters pay for
Venus-tag cards at 3 M€ - copy the Psychrophiles path in `Operation_nmM`), 232 Io Sulphur Research
(conditional draw), 247 Sponsored Academies (discard then draw 3, opponents draw 1), 256 Venus
Magnetizer, 258 Venus Waystation (`onPay_tagVenus:2`), 259 Venusian Animals (`play_tagScience:res`).

**Tests**
- `testAllVenusCardsParse` - for every card with deck `Venus`, each of `r/a/e` parses with
  `OpExpression::arr` and every leaf op resolves via `getOperationInstanceFromType` (catches typos
  across all 49).
- `testAllVenusCardsHaveValidPre` - every `pre` evaluates without exception.
- Batch A: `testGiantSolarShade` (+6%, +3 TR), `testSulphurExportsCountsItself`,
  `testGyropolisVenusAndEarth`, `testTerraformingContractNeeds25TR`, `testLuxuryFoodsVp`.
- Batch B: `testAirScrappingExpeditionOnlyVenusTargets` (a Jovian floater card is not a target, a
  Venus floater card is), `testAtmoscoopChoiceTempOrVenus`, `testExtractorBalloonsSpend2Floaters`,
  `testStratosphericBirdsRequiresFloater` (unplayable with 0 floaters anywhere, consumes one),
  `testSulphurEatingBacteriaTripleMC`, `testCorroderSuitsAnyResourceVenusCard`,
  `testHydrogenToVenusPerJovian`, `testFloatingHabsVp` (`resCard/2`).
- Batch C: `testCometForVenusTargetsOnlyVenusTagPlayers`, `testDirigiblesPayVenusCard` (floaters
  offered for a Venus card, not for a non-Venus card, rate 3), `testVenusWaystationDiscount`,
  `testVenusianAnimalsScienceTrigger`, `testSponsoredAcademiesOpponentsDraw`,
  `testIoSulphurResearchDraw1Or3`.
- Cross-expansion: `testVenusFloaterCardWithColoniesFloaterTarget` - "add a floater to ANY card" can
  target a Colonies floater card such as Titan Air-scrapping when both are on.

---

## Step 9 - Corporations

Un-comment `#14`-`#18` in `misc/cards_material.csv` (or move them into `venus_material.csv` as
`card_corp_{num}`) and give them rules. Keep `deck=Venus` so Step 1 gating applies.

| Corp | Implementation |
|------|----------------|
| Aphrodite | `e`: `raise_v:2m:this:any` (event from Step 2). |
| Manutech | Hook in `effect_incProduction`: if owner has `card_corp_16` and delta > 0, gain that many of the resource. The starting `ps` must trigger it too ("including this"). |
| Morning Star Inc. | `r`: `2pdeltav` (Step 4) plus first action: new op `revealuntil(tagVenus,3)`. |
| Celestic | `a`: `ores(Floater)`, `'holds'=>'Floater'`, `vp` `resCard/3`; first action `revealuntil(holdsFloater,2)`. Define "floater icon" as cards with `holds == Floater` or with `Floater` in their expressions. |
| Viron | New op `reuse`: choose one of your own blue cards (or corp) whose action was already used this generation and activate it again. Look at how `activate` marks cards as used. |

New op `revealuntil(cond,count)`: reveal from `deck_main` until N matches, matches go to hand,
others to discard. Log revealed cards publicly (they are public in the physical game).

**Tests**
- `testAphroditeGainsOnAnyVenusRaise` - opponent raises Venus 2 steps: Aphrodite owner +4 M€.
- `testAphroditeGainsOnWgtRaise` (Q2).
- `testManutechStartingProduction` - after playing the corp: steel production 1 and steel 1.
- `testManutechGainsOnProductionIncrease` - `2pe` -> energy +2. `testManutechNoGainOnDecrease`.
- `testMorningStarRevealUntil3Venus` - with a stacked deck: 3 Venus cards to hand, the rest to discard.
- `testMorningStarDeltaVenusOnly`.
- `testCelesticRevealUntil2Floater` and `testCelesticActionAndVp`.
- `testVironReusesUsedAction` - use an action, then Viron: the same action runs again; with no
  used actions the Viron action is void.
- `testVironCannotReuseItself`.

---

## Step 10 - Client UI (alpha level)

- Venus track: add `tracker_v_param` to the params panel (`terraformingmars_terraformingmars.tpl`,
  around `tracker_o_param`) and handle `tracker_v` in the param switch in `src/GameXBody.ts`
  (~lines 1416-1440, 2014-2025, 2158). A plain CSS scale is fine for alpha. Mark 8% and 16% bonuses.
- Body class `exp-venus` (as `exp-colonies`, `GameXBody.ts:197`) to toggle Venus-only UI.
- Tag icon `tagVenus` and floater resource icons on cards. Floaters already exist for Colonies, so
  check the Venus tag icon.
- Venus city hexes in the space area, next to Phobos/Ganymede.
- 6 milestones/awards layout.
- WGT prompt shows "World Government" context and the four param buttons.
- Jest/mocha: `tests/*.spec.ts`, add a small test for any new pure helper (e.g. Venus scale position).

Manual checklist on BGA Studio (`npm run stage -- terraformingmarsjmerrild`): create a 2p game with
Venus, play to generation 2 to see WGT, play one card per batch, claim Hoverlord, check the log has no
hidden-info leaks for reveal-until.

---

## Step 11 - Wrap up

- `npm run predeploy` (build, lint, test, jstest).
- `CHANGELOG.md` entry "Venus Next (alpha)".
- Update `TODO.md` with the known gaps: card art, polish.
- Keep the option flagged `"alpha": true` until a few studio games have been played.

## Suggested order and size

| Step | Depends on | Size |
|------|-----------|------|
| 1 option | - | S |
| 2 track + Air Scrapping | 1 | M |
| 3 tag | 1 | S |
| 4 requirements | 2 | M |
| 5 WGT | 2 | M |
| 6 milestone/award | 3 | S |
| 7 Venus cities | 1 | S |
| 8A/8B/8C cards | 2-4, 7 | L |
| 9 corps | 2, 4, 8 | M-L |
| 10 client | 2, 5, 6, 7 | M |
| 11 wrap-up | all | S |
