# Venus Next alpha - work orchestration

Each file in this folder is a self-contained brief for one implementation step. Hand an agent the brief and
nothing else: each one repeats the project primer, the rules decisions and the shared names it needs.
The overall plan and its reasoning are in [../../VENUS_NEXT_PLAN.md](../../VENUS_NEXT_PLAN.md). Card data
(49 project cards, 5 corporations) is in [../../misc/venus_next_cards.json](../../misc/venus_next_cards.json).

**Branch:** all Venus work lives on the `venus-next` branch, not `main`. Every agent branches from the
latest `venus-next` and merges its finished step back into `venus-next`. `venus-next` goes to `main` only
when the whole alpha is done (after step 11), and only when the user decides.

## Briefs

| Step | Brief | Depends on | Size |
|------|-------|-----------|------|
| 1 | [step-01-option.md](step-01-option.md) - game option, variant plumbing, Venus material file | - | M |
| 2 | [step-02-venus-track.md](step-02-venus-track.md) - Venus parameter, `v` op, bonuses, Air Scrapping | 1 | M |
| 3 | [step-03-venus-tag.md](step-03-venus-tag.md) - Venus tag counting | 1 | S |
| 4 | [step-04-requirements.md](step-04-requirements.md) - Venus requirements and modifiers | 2 | M |
| 5 | [step-05-wgt.md](step-05-wgt.md) - World Government Terraforming, solo win rule | 2 | M |
| 6 | [step-06-milestone-award.md](step-06-milestone-award.md) - Hoverlord and Venuphile | 3 | S |
| 7 | [step-07-venus-cities.md](step-07-venus-cities.md) - off-Mars Venus city areas | 1 | S |
| 8A | [step-08a-cards-simple.md](step-08a-cards-simple.md) - cards that only need existing ops | 2, 3, 4, 7 | M |
| 8B | [step-08b-cards-resources.md](step-08b-cards-resources.md) - floater/microbe/animal cards | 8A | L |
| 8C | [step-08c-cards-special.md](step-08c-cards-special.md) - cards with special rules | 8B | M |
| 9 | [step-09-corporations.md](step-09-corporations.md) - the 5 corporations | 2, 4, 8B | M-L |
| 10 | [step-10-client-ui.md](step-10-client-ui.md) - client UI | 2, 5, 6, 7 | M |
| 11 | [step-11-wrap-up.md](step-11-wrap-up.md) - predeploy, changelog, TODO, staging | all | S |

Step 8 is split into three briefs (one per card batch) because 49 cards is too much for one session.

## Running order

```
1 ──┬── 2 ──┬── 4 ──┐
    │       └── 5 ──┼───────────────┐
    ├── 3 ── 6 ─────┼───────────────┤
    └── 7 ──────────┴── 8A ─ 8B ─ 8C ── 9 ── 11
                                    10 ─┘   (10 after 2, 5, 6, 7)
```

- After step 1, steps 2, 3 and 7 can run in parallel. After step 2, steps 4 and 5 can run in parallel.
- Almost every step edits `modules/PGameXBody.php` and `misc/venus_material.csv`. Merge each step into
  `venus-next` before starting a step that depends on it, and rebase parallel step branches onto
  `venus-next` before merging.
- Keep `venus-next` up to date with `main` by merging `main` into `venus-next` (not the other way round)
  when `main` gets fixes the Venus work needs.
- Every step ends with `npm run test` green and adds its tests to `modules/tests/VenusTest.php`.

## Shared names (source of truth)

Every brief uses these names. If a step has to change one, update this table and every brief.

| Name | Introduced in | Meaning |
|------|---------------|---------|
| Game option `111`, `$varname` `venus`, game state `var_venus` | 1 | "Expansion: Venus Next", alpha |
| `PGameXBody::isVenusVariant(): bool` | 1 | Venus Next on |
| `GameUT::init(int $map = 0, int $colonies = 0, int $venus = 0)` | 1 | test harness switch |
| `misc/venus_material.csv`, material section `venus_material` | 1 | all Venus material |
| deck `Venus` | 1 | deck column for Venus cards and corps |
| `card_main_213` ... `card_main_261` | 1 | project cards |
| `card_corp_14` Aphrodite, `_15` Celestic, `_16` Manutech, `_17` Morning Star Inc., `_18` Viron | 1 | corporations |
| `modules/tests/VenusTest.php` | 1 | all Venus tests |
| `tracker_v` (type `param`, location `params`, 0..30) | 2 | Venus scale, 2% per step |
| op `v` / `Operation_v` | 2 | raise Venus 1 step (`2v` = 2 steps) |
| `param_v_8` (draw), `param_v_16` (tr) | 2 | track bonuses |
| trigger event `raise_v`, fired once **per step** | 2 | for Aphrodite (listener `raise_v:2m:this:any`) |
| `effect_increaseParam(..., ["wgt" => true])` | 5 | raise without TR and without bonuses |
| `card_stanproj_9` "Air Scrapping", 15 M€, rules `v` | 2 | standard project |
| `tracker_tagVenus_<color>` | 3 | Venus tag count |
| `tracker_pdeltav_<color>`, op `pdeltav`, evaluate option `vmods` | 4 | Venus-only requirement delta (Morning Star) |
| op `wgt` / `Operation_wgt` | 5 | World Government step |
| `isSoloTerraformingComplete()` | 5 | solo win check including Venus |
| `milestone_6` Hoverlord, `award_6` Venuphile | 6 | added on every map when Venus is on |
| hexes named `Dawn City`, `Luna Metropolis`, `Maxwell Base`, `Stratopolis` (`inspace=1`, `reserved=1`) | 7 | off-Mars city areas |
| op `ores(Any,<Tag>)` | 8B | add 1 resource to your card with that tag; the type is whatever the target holds |
| op `draw(<Tag>)`, `draw(Floater)` | 9 | reveal until N match (existing Prelude op; `Floater` = floater icon, `hasFloaterIcon`) |
| op `reuse` | 9 | Viron: reuse an action used this generation |

## Rules decisions (apply to every step)

1. Venus scale: 0-30% in 2% steps (15 steps). Each step gives the raising player 1 TR. Bonuses: reaching 8%
   draws a card; reaching 16% gives 1 extra TR.
2. Venus is **not** part of the Mars end-of-game condition (multiplayer ends when temperature, oxygen and
   oceans are maxed).
3. Adaptation Technology, Special Design **and** Inventrix modify Venus requirements too. Morning Star Inc.
   modifies only Venus requirements, stacking with the others.
4. World Government Terraforming (WGT): in the solar phase after production, if the game is not ending, the
   first player of the generation raises one non-maxed parameter (temperature, oxygen, ocean or Venus).
   No TR and no bonuses of any kind (no track bonuses, no ocean/tile placement bonuses).
5. WGT raising Venus **does** trigger Aphrodite.
6. Solo with Venus Next (standard flavour): winning requires all four parameters maxed, Venus included.
   Still 14 TR and 14 generations. WGT runs every generation, the solo player chooses, and it is skipped in
   the last generation because the end check comes first. The TR63 flavour is unchanged.
7. Off-Mars Venus cities count as city tiles in play but **not** as cities on Mars.
8. Hoverlord milestone: 7 floaters on your cards. Venuphile award: most Venus tags. Still max 3 claimed and
   3 funded.

## Status

Tick a box when the step's branch is merged.

- [x] 1  - [x] 2  - [x] 3  - [x] 4  - [x] 5  - [ ] 6  - [x] 7
- [x] 8A - [x] 8B - [x] 8C - [x] 9  - [ ] 10 - [ ] 11
