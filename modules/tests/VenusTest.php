<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Venus Next tests. Plan: VENUS_NEXT_PLAN.md, step briefs: docs/venus-next/.
 *
 * Rules decisions (Step 0), tests below must follow these:
 *
 * Q1 Adaptation Technology, Special Design and Inventrix modify Venus requirements too.
 *    The general delta (tracker_pdelta, onPre_delta) applies to t/o/w and v. Morning Star Inc.
 *    adds a Venus-only delta (tracker_pdeltav) on top, stacking with the general one.
 * Q2 World Government Terraforming raising Venus triggers Aphrodite (raise_v fires).
 * Q3 WGT gives no TR and no bonuses of any kind: no track bonuses (O2 8% temp, temp ocean,
 *    Venus 8%/16%) and no ocean/tile placement bonuses.
 * Q4 Solo (official rules, standard flavour): winning requires all four parameters maxed,
 *    Venus included. Still 14 TR and 14 generations. WGT runs every generation, the solo player
 *    (always first player) chooses, and it is skipped in the last generation because the Game End
 *    Check comes first. The TR63 flavour is unchanged.
 * Q5 Off-Mars Venus cities (Dawn City, Luna Metropolis, Maxwell Base, Stratopolis) count as city
 *    tiles in play but not as cities on Mars (same as Phobos Space Haven).
 */
final class VenusTest extends TestCase {
    private function venusJson(): array {
        return json_decode(file_get_contents(__DIR__ . "/../../misc/venus_next_cards.json"), true);
    }

    public function testVenusSourceDataIsComplete() {
        $data = $this->venusJson();
        $this->assertEquals(range(213, 261), array_column($data["projectCards"], "num"));
        $this->assertCount(5, $data["corporations"]);
    }

    public function testVenusOptionOffCreatesNoVenusTokens() {
        $m = (new GameUT())->init(0, 0, 0);
        foreach ($m->token_types as $key => $info) {
            if (array_get($info, "deck") == "Venus") {
                $this->assertNull($m->tokens->getTokenInfo($key), $key);
            }
        }
    }

    public function testVenusOptionOnCreatesVenusTokens() {
        $m = (new GameUT())->init(0, 0, 1);
        for ($num = 213; $num <= 261; $num++) {
            $this->assertEquals("deck_main", $m->tokens->getTokenLocation("card_main_$num"), "card_main_$num");
        }
        for ($num = 14; $num <= 18; $num++) {
            $this->assertEquals("deck_corp", $m->tokens->getTokenLocation("card_corp_$num"), "card_corp_$num");
        }
    }

    public function testVenusMaterialMatchesJson() {
        $m = (new GameUT())->init(0, 0, 1);
        $data = $this->venusJson();
        foreach ($data["projectCards"] as $card) {
            $id = "card_main_" . $card["num"];
            $this->assertEquals($card["cost"], $m->getRulesFor($id, "cost"), $id);
            $this->assertEquals($card["name"], $m->getRulesFor($id, "name"), $id);
            $this->assertEquals("Venus", $m->getRulesFor($id, "deck"), $id);
            $this->assertEquals(implode(" ", $card["tags"]), $m->getRulesFor($id, "tags", ""), $id);
        }
        foreach ($data["corporations"] as $corp) {
            $id = "card_corp_" . $corp["num"];
            $this->assertEquals(-$corp["startingMC"], $m->getRulesFor($id, "cost"), $id);
            $this->assertEquals($corp["name"], $m->getRulesFor($id, "name"), $id);
            $this->assertEquals(implode(" ", $corp["tags"]), $m->getRulesFor($id, "tags", ""), $id);
        }
    }

    // Step 2: Venus scale, v operation, track bonuses, Air Scrapping

    private function venusGame(int $venus = 1): GameUT {
        return (new GameUT())->init(0, 0, $venus);
    }

    private function raiseVenus(GameUT $m, string $op = "v", string $color = PCOLOR) {
        $m->push($color, $op);
        $m->st_gameDispatch();
    }

    public function testRaiseVenusIncreasesTrackAndTR() {
        $m = $this->venusGame();
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->raiseVenus($m);
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 1, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testRaiseVenus2Steps() {
        $m = $this->venusGame();
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->raiseVenus($m, "2v");
        $this->assertEquals(4, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 2, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testVenusBonusAt8DrawsCard() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 6);
        $this->raiseVenus($m);
        $this->assertEquals(8, $m->tokens->getTokenState("tracker_v"));
        // the bonus queues a draw, which waits for the player to confirm since it cannot be undone
        $tops = $m->machine->getTopOperations(PCOLOR);
        $op = reset($tops);
        $this->assertEquals("draw", $op["type"]);
        $this->assertEquals(PCOLOR, $op["owner"]);
        $this->assertEquals(1, $op["count"]);
    }

    public function testVenusBonusAt16GivesTR() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 14);
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->raiseVenus($m);
        $this->assertEquals(16, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 2, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testVenusCapsAt30() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 28);
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->raiseVenus($m, "2v");
        $this->assertEquals(30, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 1, $m->getTrackerValue(PCOLOR, "tr"));
        $op = $m->getOperationInstanceFromType("v", PCOLOR);
        $this->assertTrue($op->requireConfirmation());
    }

    public function testRaiseVenusFiresTriggerPerStep() {
        $m = $this->venusGame();
        $m->setListeners([
            "card_x" => ["e" => "raise_v:m:this:any", "owner" => BCOLOR, "key" => "card_x"],
        ]);
        $this->raiseVenus($m, "2v");
        $triggered = array_filter($m->machine->getTopOperations(), fn($op) => $op["owner"] == BCOLOR && $op["type"] == "m" && $op["data"] == "card_x:e:card_x");
        $this->assertCount(2, $triggered);
    }

    public function testVenusDoesNotAffectEndOfGame() {
        $m = $this->venusGame();
        foreach (["t", "o", "w"] as $p) {
            $m->tokens->setTokenState("tracker_$p", $m->getRulesFor("tracker_$p", "max"));
        }
        $this->assertTrue($m->isEndOfGameAchived());
        $this->assertEquals(0, $m->getVenusProgression());

        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 30);
        $this->assertFalse($m->isEndOfGameAchived());
        $this->assertEquals(100, $m->getVenusProgression());
    }

    public function testVenusRequirementTermUsesStepsOf2() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 6);
        $this->assertEquals(0, $m->evaluateExpression("v>=10", PCOLOR));
        $this->assertEquals(1, $m->evaluateExpression("v>=10", PCOLOR, null, ["mods" => 2]));
        $this->assertEquals(0, $m->evaluateExpression("v>=10", PCOLOR, null, ["mods" => 1]));
    }

    public function testAirScrappingOnlyWithVenus() {
        $m = $this->venusGame(0);
        $this->assertNull($m->tokens->getTokenInfo("card_stanproj_9"));

        $m = $this->venusGame();
        $this->assertNotNull($m->tokens->getTokenInfo("card_stanproj_9"));
        $m->setTrackerValue(PCOLOR, "m", 15);
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $m->push(PCOLOR, "stan");
        $tops = $m->machine->getTopOperations(PCOLOR);
        $op = reset($tops);
        $m->fakeUserAction($op, "card_stanproj_9");
        $m->st_gameDispatch();
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "m"));
        $this->assertEquals($tr + 1, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testNoVenusTrackerWithoutVenus() {
        $m = $this->venusGame(0);
        $this->assertNull($m->tokens->getTokenInfo("tracker_v"));
        $this->assertEquals(0, $m->getVenusProgression());
    }

    // Step 3: Venus tag

    public function testPlayVenusCardCountsTag() {
        $m = $this->venusGame();
        $m->effect_playCard(PCOLOR, "card_main_233"); // Ishtar Mining
        $this->assertEquals(1, $m->getTrackerValue(PCOLOR, "tagVenus"));
        $this->assertEquals(0, $m->getTrackerValue(BCOLOR, "tagVenus"));
    }

    public function testVenusGovernorCountsTwoTags() {
        $m = $this->venusGame();
        $m->effect_playCard(PCOLOR, "card_main_233"); // Ishtar Mining
        $m->effect_playCard(PCOLOR, "card_main_255"); // Venus Governor, tags Venus Venus
        $this->assertEquals(3, $m->getTrackerValue(PCOLOR, "tagVenus"));
    }

    public function testVenusTagRequirement() {
        $m = $this->venusGame();
        $color = PCOLOR;
        $card = "card_main_244"; // Sister Planet Support: Venus and Earth tags
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition($color, $card));

        $m->tokens->setTokenState("tracker_tagVenus_{$color}", 1);
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition($color, $card));
        $m->tokens->setTokenState("tracker_tagEarth_{$color}", 1);
        $this->assertEquals(MA_OK, $m->precondition($color, $card));

        $m->tokens->setTokenState("tracker_tagVenus_{$color}", 0);
        $m->tokens->setTokenState("tracker_tagWild_{$color}", 1);
        $this->assertEquals(MA_OK, $m->precondition($color, $card));
    }

    public function testSingleWildDoesNotSatisfyTwoTagRequirement() {
        $m = $this->venusGame();
        $color = PCOLOR;
        $m->tokens->setTokenState("tracker_tagWild_{$color}", 1);
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition($color, "card_main_244")); // Sister Planet Support
        $m->tokens->setTokenState("tracker_tagWild_{$color}", 2);
        $this->assertEquals(MA_OK, $m->precondition($color, "card_main_244"));
    }

    public function testVenusGovernorRequirementCountsWild() {
        $m = $this->venusGame();
        $color = PCOLOR;
        $card = "card_main_255"; // Venus Governor: 2 Venus tags
        $m->tokens->setTokenState("tracker_tagVenus_{$color}", 1);
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition($color, $card));
        $m->tokens->setTokenState("tracker_tagWild_{$color}", 1);
        $this->assertEquals(MA_OK, $m->precondition($color, $card));
    }

    public function testVenusTagTriggersPlayTagVenus() {
        $m = $this->venusGame();
        // no card reacts to Venus tags yet, so give a tableau card that effect
        $m->token_types["card_main_1"]["e"] = "play_tagVenus:m";
        $m->tokens->moveToken("card_main_1", "tableau_" . PCOLOR, MA_CARD_STATE_ACTION_UNUSED);
        $m->clearEventListenerCache();
        $this->assertCount(1, $m->collectListeners(PCOLOR, "play_tagVenus"));
        $before = $m->getTrackerValue(PCOLOR, "m");
        $m->effect_playCard(PCOLOR, "card_main_233"); // Ishtar Mining
        $m->gamestate->jumpToState(STATE_GAME_DISPATCH);
        $m->st_gameDispatch();
        $this->assertEquals($before + 1, $m->getTrackerValue(PCOLOR, "m"));
    }

    public function testUniqueTagsIncludesVenus() {
        $m = $this->venusGame();
        $before = $m->evaluateExpression("uniquetags", PCOLOR);
        $m->effect_playCard(PCOLOR, "card_main_233"); // Ishtar Mining
        $this->assertEquals($before + 1, $m->evaluateExpression("uniquetags", PCOLOR));
        $m->effect_playCard(PCOLOR, "card_main_255"); // Venus Governor, same tag again
        $this->assertEquals($before + 1, $m->evaluateExpression("uniquetags", PCOLOR));
    }

    public function testUniqueTagsCapDependsOnVenus() {
        // 10 base tags (+ Venus when on); wilds can fill the gaps but not exceed the number of real tags
        foreach ([0 => 10, 1 => 11] as $venus => $max) {
            $m = (new GameUT())->init(0, 0, $venus);
            $color = PCOLOR;
            $m->tokens->setTokenState("tracker_tagWild_{$color}", 20);
            $this->assertEquals($max, $m->evaluateExpression("uniquetags", $color), "venus=$venus");
        }
    }

    public function testVenusTagZeroWithoutVenus() {
        $m = (new GameUT())->init(0, 0, 0);
        $this->assertEquals(0, $m->evaluateExpression("tagVenus", PCOLOR));
    }

    public function testAllPreconditionsParseCompletely() {
        // MathExpression is binary only and ignores trailing tokens: "a + b >= 2" silently drops ">= 2"
        $m = $this->venusGame();
        foreach ($m->token_types as $key => $info) {
            $pre = array_get($info, "pre");
            if (!$pre) {
                continue;
            }
            $parser = new MathExpressionParser($pre);
            $parser->parseExpression();
            $this->assertTrue($parser->isEos(), "$key: $pre");
        }
    }
}
