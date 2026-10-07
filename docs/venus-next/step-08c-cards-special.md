# Venus Next - Step 8C: project cards with special rules

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
  `r` immediate, `a` action, `e` triggered effect (`event:outcome[:context[:any]]`, several joined with `;`).
- Op expressions: syntax at the top of `modules/OpExpression.php`. Op types map to
  `modules/operations/Operation_<type>.php` or a class in `misc/op_material.csv`. Relevant examples:
  - discounts: Teractor `e` = `onPay_tagEarth:3m` (collected by `collectDiscounts`, ~line 1603)
  - tag triggers: Decomposers `play_tagPlant:res;play_tagMicrobe:res;play_tagAnimal:res`
  - "remove from any player": Sabotage `3nu_Any/4ns_Any/7nm_Any/nop` (`Operation_nR_Any`)
  - conditional count: `counter('(tagScience+1)/3'):m` (see `GameTest::testEvaluteCounter`)
  - production for TR: Equatorial Magnetizer #15 (`grep "^15|" misc/cards_material.csv`)
  - alternative payment with card resources: Psychrophiles P39 in `modules/operations/Operation_nmM.php`
    (~lines 130, 279, 299 - microbes pay 2 M€ each for plant cards)
- Tests: `npm run test` (PHPUnit), one class: `npm run test -- --filter VenusTest`. Harness
  `modules/tests/GameUT.php`, players `PCOLOR`, `BCOLOR`. Helpers: `mtFind`, `effect_playCard`,
  `st_gameDispatch`, `fakeUserAction`, `machine->getTopOperations`, `getPayment($color, $card)`,
  `getOperationInstanceFromType($type, $color)->argPrimaryDetails()`.
- Before committing: `npm run test` and `npm run lint:php`. End of session:
  `npm run build:ts && npm run build:scss && npm run stage -- terraformingmarsjmerrild`.

## Already available

Steps 1-4, 7, 8A, 8B: all other Venus cards have rules; `ores(Type,Tag)` targeting; `testAllVenusCardsParse`.
Source of truth for card text: `misc/venus_next_cards.json`.

## Cards in this batch

| # | Card | Rules |
|---|------|-------|
| 218 | Comet for Venus (event) | Raise Venus 1 step. Remove up to 4 M€ from any player **with a Venus tag in play**. Proposed `r`: `v,4nm_Any(Venus)/nop`-style - extend `Operation_nR_Any` with an optional tag filter on the target player (opponent must have `tagVenus>=1`), keep "up to". |
| 222 | Dirigibles (active) | `a` `ores(Floater)` (ANY card). Effect: when paying for a card **with a Venus tag**, floaters here can pay 3 M€ each. Implement like Psychrophiles in `Operation_nmM`. |
| 232 | Io Sulphur Research | Draw 1 card, or 3 if you have at least 3 Venus tags. `r` e.g. `counter('1+2*(tagVenus>=3)') draw` - check MathExpression supports comparison inside arithmetic; otherwise add a small term. |
| 247 | Sponsored Academies | Discard 1 card from hand, THEN draw 3; all opponents draw 1. `r` `discard,3draw,<opponents draw 1>` - check whether a "for each opponent" op exists; otherwise add one (e.g. `draw_opp`). Discard is mandatory: card unplayable with an empty hand (after paying, the card itself left the hand). |
| 256 | Venus Magnetizer (active) | `a` `npe:v` (like Equatorial Magnetizer). |
| 258 | Venus Waystation (active) | `e` `onPay_tagVenus:2m`; VP 1. |
| 259 | Venusian Animals (active) | `e` `play_tagScience:res`; vp `resCard`; holds Animal. "including this" - its own Science tag adds an animal when played. |

## Tasks

1. Fill the rules in `misc/venus_material.csv`; add the new op forms; `npm run build:material`.
2. Dirigibles payment: payment options must include "floaters from Dirigibles at 3" only for cards with a
   Venus tag (including Venus standard project? No - only cards). Client payment UI may need a resource
   type entry; if so, make the minimal client change (look at how P39 microbes show in `src/GameXBody.ts`)
   or note it for step 10.
3. Hidden information: Sponsored Academies' opponent draws go to their private hands - use the same draw
   helper as other draw effects so nothing leaks via `notifyAllPlayers`.

## Tests (append to `modules/tests/VenusTest.php`)

- `testCometForVenusTargetsOnlyVenusTagPlayers` - BCOLOR without Venus tag: not a target; with one: loses up
  to 4 M€ (3 if only 3).
- `testDirigiblesPayVenusCard` - 2 floaters on Dirigibles: offered when paying for a Venus-tag card
  (6 M€ worth), not offered for a non-Venus card.
- `testVenusWaystationDiscount` - Venus card costs 2 less; non-Venus card unchanged.
- `testVenusianAnimalsScienceTrigger` - playing it adds 1 animal (own Science tag); a later Science card
  adds another.
- `testSponsoredAcademiesOpponentsDraw` - hand: discard 1, draw 3; BCOLOR hand +1.
- `testSponsoredAcademiesNeedsCardToDiscard` - unplayable when it is the only card in hand.
- `testIoSulphurResearchDraw1Or3` - 2 Venus tags: +1 card; 3 Venus tags: +3 cards (+ its own 0).
- `testVenusMagnetizer` - energy production 1 -> 0, Venus +2%, TR +1; void at energy production 0.

## Done when

- All tests pass, `npm run lint:php` clean. All 49 Venus project cards have rules matching their text.
- Commit: `feat(venus): rules for special Venus project cards`.

## Out of scope

Corporations (step 9), payment UI polish (step 10).
