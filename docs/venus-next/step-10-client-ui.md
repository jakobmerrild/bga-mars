# Venus Next - Step 10: client UI (alpha level)

You are implementing one step of the Venus Next (alpha) expansion. This brief is self-contained.
Overall plan: `VENUS_NEXT_PLAN.md`. Shared names and the full step list: `docs/venus-next/README.md`.

**Branch:** all Venus work lives on `venus-next`, not `main`. Branch from the latest `venus-next`
(it must already contain the steps this one depends on), and merge your finished step back into
`venus-next`. Do not merge into or open PRs against `main`.

## Project primer

- BGA implementation of Terraforming Mars (`terraformingmars`): PHP 8.4 server, TypeScript + SCSS client,
  legacy (non-namespaced) BGA framework. Read `CLAUDE.md`, `DESIGN.md` and `README.md` (client section).
- Client: `src/*.ts` compile to `terraformingmars.js` (`npm run build:ts`; `src/Zain.ts` is the dojo
  entry), `src/css/GameXBody.scss` (imports the others) compiles to `terraformingmars.css`
  (`npm run build:scss`). Static layout: `terraformingmars_terraformingmars.tpl`. Never edit the generated
  `.js`/`.css`. Main file: `src/GameXBody.ts`. Card text/icons from op expressions: `src/CustomRenders.ts`.
  Two layouts: cardboard (`src/css/Map.scss` etc.) and digital/vertical (`src/css/VLayout.scss`).
- Material is shared with the client via `this.getRulesFor(tokenId, field)`.
- JS tests: `npm run jstest` (mocha, `tests/*.spec.ts`).
- Testing in the real client: `npm run build:ts && npm run build:scss && npm run stage --
  terraformingmarsjmerrild`, then upload `dist/terraformingmarsjmerrild/` (sftp profile `mars-jmerrild`)
  and play on BGA Studio. You may not be able to do this yourself - if not, list exactly what the user
  should check.

## Already available (server side complete)

- Option `var_venus`; body class `exp-venus` may already exist from step 7 (`src/GameXBody.ts` ~line 197,
  next to `exp-colonies`).
- `tracker_v` (0..30, param), track bonuses at 8 and 16, `card_stanproj_9` Air Scrapping.
- `tracker_tagVenus_<color>` (CSS `.tracker_tagVenus` exists in `src/css/PlayerBoard.scss` ~line 238).
- `wgt` op (choose t/o/w/v, then an ocean hex if ocean), `draw(Venus)`/`draw(Floater)` (reveal until), `reuse` ops.
- `milestone_6` Hoverlord, `award_6` Venuphile.
- Hexes `hex_0_4`..`hex_0_7` (Dawn City, Luna Metropolis, Maxwell Base, Stratopolis) with placeholder
  divs and positions from step 7.
- 49 Venus cards (`card_main_213..261`) and corps `card_corp_14..18`, deck `Venus`.

## Tasks

1. **Venus track.** Add a `tracker_v_param` line to the params panel in the tpl (next to
   `tracker_o_param` ~line 454) and a Venus scale on the board (next to `oxygen_map` / `temperature_map`,
   ~lines 34-90; a plain CSS scale with 16 positions is fine for alpha). Mark the 8% and 16% bonuses.
   Handle `tracker_v` wherever `tracker_o` is handled in `src/GameXBody.ts`: tooltip switch (~lines
   1408-1440), counter update (~2014-2025), param copy (~2152-2160) and the progress code (~3603). Hide all
   of it without Venus.
2. **Venus tag** in the player board tag list and in card tag rendering (check `tag-venus` / `tagVenus`
   icons exist in `img/` sprites; if not, use a CSS fallback - a coloured circle with "V").
3. **Card rendering.** Venus cards have no art: make sure title, cost, tags, requirement, VP and text
   render readably (follow how Colonies cards without art render). In `src/CustomRenders.ts` add
   `ores(Floater,Venus)` and similar to the replacements next to `ores(Floater,Jovian)` (~lines 236-237,
   339) so icons show. Show Venus requirements (`v>=10` -> "10% Venus", `v<=14` -> "max 14% Venus").
   Multi-tag requirements (Luxury Foods, Mining Quota, Omnicourt, Sister Planet Support, Solarnet) use the
   `(((tagVenus>0) + (tagEarth>0)) + tagWild) >= 2` form; the tooltip only special-cases Advanced
   Ecosystems (`card_main_135`, `src/GameXBody.ts` ~line 1585), so give these readable text too.
4. **World Government prompt**: the `wgt` op's buttons say "World Government: raise Temperature / Oxygen /
   Ocean / Venus"; it is clear the active player chooses on behalf of the World Government.
5. **Milestones/awards row** fits 6 entries in both layouts.
6. **Venus city hexes**: final positions and a Venus background area for the four hexes (both layouts,
   all maps - see the per-map `.hex_phobos` rules in `VLayout.scss` ~lines 469-515).
7. **Payment**: Dirigibles floaters appear as a payment option when paying for a Venus card (if not
   already done in step 8C).
8. **Revealed cards** from `draw(Venus)`/`draw(Floater)` are shown in the log.
9. **Morning Star delta**: `tracker_pdeltav_<color>` (step 4, `player_tags_<color>`) has a tooltip but no
   icon; give it one like `tracker_pdelta` (`.tracker_pdelta` in `GameXBody.scss` / `PlayerBoard.scss`).

## Tests

- `npm run jstest`: add a spec for any pure helper you write (e.g. Venus scale position from value, the
  requirement text formatter).
- Manual checklist (write the results into your summary): 2-player Venus game on Tharsis and Hellas;
  Venus track updates on raise; WGT prompt at end of generation 1; Air Scrapping in standard projects;
  play one card from each batch (Giant Solar Shade, Stratopolis, Dirigibles); claim Hoverlord; both
  layouts; no console errors; Venus off game shows nothing Venus-related.

## Done when

- `npm run build:ts`, `npm run build:scss`, `npm run jstest` pass; `npm run test` still green.
- Commit: `feat(venus): client UI for Venus Next`.

## Out of scope

Real card art and Venus board graphics.

## As implemented

- Venus board is a separate `#venus_board` next to `#main_board` (tpl, `src/css/Venus.scss`): scale, the four
  city hexes and Air Scrapping (`card_stanproj_9` redirected to `venus_stanproj`). All Venus CSS is in
  `Venus.scss`, imported last; CSS stand-ins for the Venus icon, tag, any-resource icon and card illustration.
- Hoverlord / Venuphile are not in the milestone/award row: like the physical tiles they sit on the
  Milestones / Awards banner (`#milestones_extra`, `#awards_extra`), per-map positions for Amazonis.
- Venus cards show the generated card face in the digital layout too (no scans).
- Not tested on BGA Studio yet - the manual checklist above is still open.
