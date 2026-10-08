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

    // Step 7 - off-Mars Venus city areas

    const VENUS_CITY_HEXES = [
        "hex_0_4" => "Dawn City",
        "hex_0_5" => "Luna Metropolis",
        "hex_0_6" => "Maxwell Base",
        "hex_0_7" => "Stratopolis",
    ];

    private function placeCity(GameUT $m, string $optype, string $hex, string $color = PCOLOR) {
        $m->push($color, $optype);
        $tops = $m->machine->getTopOperations($color);
        $op = reset($tops);
        $m->fakeUserAction($op, $hex);
        $m->st_gameDispatch();
        $m->clean_cache();
    }

    public function testVenusCityHexesOnlyWithVenus() {
        for ($map = 0; $map <= 4; $map++) {
            $m = (new GameUT())->init($map, 0, 1);
            foreach (self::VENUS_CITY_HEXES as $hex => $name) {
                $this->assertEquals($name, $m->getRulesFor($hex, "name", null), "map $map $hex");
                $this->assertEquals(1, $m->getRulesFor($hex, "inspace"), "map $map $hex");
                $this->assertEquals(1, $m->getRulesFor($hex, "reserved"), "map $map $hex");
            }
            $m = (new GameUT())->init($map, 0, 0);
            $planet = $m->getPlanetMap(false);
            foreach (self::VENUS_CITY_HEXES as $hex => $name) {
                $this->assertArrayNotHasKey($hex, $planet, "map $map $hex");
            }
            $this->assertArrayHasKey("hex_0_3", $planet, "map $map");
        }
    }

    public function testVenusCityCardRulesParse() {
        $m = (new GameUT())->init(0, 0, 1);
        foreach ([220, 236, 238, 248] as $num) {
            $r = $m->getRulesFor("card_main_$num", "r");
            $this->assertNotEmpty(OpExpression::arr($r), "card_main_$num");
        }
    }

    public function testDawnCityPlacesOnReservedArea() {
        $m = (new GameUT())->init(0, 0, 1);
        $op = $m->getOperationInstanceFromType("city('Dawn City')", PCOLOR);
        $targets = [];
        foreach ($op->argPrimaryDetails() as $hex => $info) {
            if ($info["q"] == MA_OK) {
                $targets[] = $hex;
            }
        }
        $this->assertEquals(["hex_0_4"], $targets);
    }

    public function testVenusCityHexNotOfferedToNormalCity() {
        $m = (new GameUT())->init(0, 0, 1);
        $details = $m->getOperationInstanceFromType("city", PCOLOR)->argPrimaryDetails();
        foreach (self::VENUS_CITY_HEXES as $hex => $name) {
            $this->assertNotEquals(MA_OK, $details[$hex]["q"] ?? MA_ERR_RESERVED, $hex);
        }
    }

    public function testVenusCityCountsAsCityNotOnMars() {
        $m = (new GameUT())->init(0, 0, 1);
        $this->placeCity($m, "city('Stratopolis')", "hex_0_7");
        $this->assertEquals(PCOLOR, $m->getPlanetMap()["hex_0_7"]["owner"]);
        $this->assertEquals(1, $m->getTrackerValue(PCOLOR, "city"));
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "cityonmars"));
        $this->assertEquals(0, $m->evaluateExpression("cityonmars", PCOLOR));
    }

    public function testTharsisRepublicVenusCity() {
        $m = (new GameUT())->init(0, 0, 1);
        $m->dbSetTokenLocation("card_corp_11", "tableau_" . PCOLOR, MA_CARD_STATE_ACTION_UNUSED);
        $m->clearEventListenerCache();
        $this->placeCity($m, "city('Luna Metropolis')", "hex_0_5");
        $this->assertEquals(3, $m->getTrackerValue(PCOLOR, "m"));
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "pm"));
    }

    public function testOnMarsCountersIgnoreVenusCities() {
        $m = (new GameUT())->init(0, 0, 1);
        $this->placeCity($m, "city('Stratopolis')", "hex_0_7");
        $this->placeCity($m, "city", "hex_4_3", BCOLOR);
        $this->assertEquals(1, $m->getTrackerValue(BCOLOR, "cityonmars"));
        // Zeppelins / Martian Rails
        $this->assertEquals(1, $m->evaluateExpression("all_cityonmars", PCOLOR));
        // Greenhouses / Energy Saving: city tiles in play
        $this->assertEquals(2, $m->evaluateExpression("all_city", PCOLOR));
    }

    // Step 6 - Hoverlord milestone and Venuphile award

    private function addFloaters(GameUT $m, string $card, int $count, string $color = PCOLOR) {
        if ($m->tokens->getTokenLocation($card) != "tableau_$color") {
            $m->dbSetTokenLocation($card, "tableau_$color", MA_CARD_STATE_ACTION_UNUSED);
        }
        for ($i = 0; $i < $count; $i++) {
            $res = $m->createPlayerResource($color);
            $m->tokens->moveToken($res, $card, 1);
        }
        $m->clean_cache();
    }

    private function claimStatus(GameUT $m, string $optype, string $target, string $color = PCOLOR) {
        $op = $m->getOperationInstanceFromType($optype, $color);
        $args = $op->argPrimaryDetails();
        $this->assertArrayHasKey($target, $args);
        return $args[$target]["q"];
    }

    public function testVenusOptionOnAddsHoverlordAndVenuphile() {
        for ($map = 0; $map <= 4; $map++) {
            $m = (new GameUT())->init($map, 0, 1);
            $this->assertEquals("Hoverlord", $m->getTokenName("milestone_6"), "map $map");
            $this->assertEquals("Venuphile", $m->getTokenName("award_6"), "map $map");
            $this->assertCount(6, $m->tokens->getTokensOfTypeInLocation("milestone_", "display_milestones"), "map $map");
            $this->assertCount(6, $m->tokens->getTokensOfTypeInLocation("award_", "display_awards"), "map $map");
            $this->assertEquals("display_milestones", $m->tokens->getTokenLocation("milestone_6"), "map $map");
            $this->assertEquals("display_awards", $m->tokens->getTokenLocation("award_6"), "map $map");
        }
    }

    public function testVenusMilestonesAbsentWithoutVenus() {
        for ($map = 0; $map <= 4; $map++) {
            $m = (new GameUT())->init($map, 0, 0);
            $this->assertNull($m->tokens->getTokenInfo("milestone_6"), "map $map");
            $this->assertNull($m->tokens->getTokenInfo("award_6"), "map $map");
            $this->assertArrayNotHasKey("milestone_6", $m->token_types, "map $map");
            $this->assertArrayNotHasKey("award_6", $m->token_types, "map $map");
            $this->assertCount(5, $m->tokens->getTokensOfTypeInLocation("milestone_", "display_milestones"), "map $map");
            $this->assertCount(5, $m->tokens->getTokensOfTypeInLocation("award_", "display_awards"), "map $map");
        }
    }

    public function testHoverlordClaim() {
        $m = (new GameUT())->init(0, 0, 1);
        $m->setTrackerValue(PCOLOR, "m", 10);
        // Aerial Mappers and Deuterium Export hold floaters
        $this->addFloaters($m, "card_main_213", 4);
        $this->addFloaters($m, "card_main_221", 2);
        $this->assertEquals(6, $m->evaluateExpression("resFloater", PCOLOR));
        $this->assertEquals(MA_ERR_PREREQ, $this->claimStatus($m, "claim", "milestone_6"));
        $this->addFloaters($m, "card_main_221", 1);
        $this->assertEquals(7, $m->evaluateExpression("resFloater", PCOLOR));
        $this->assertEquals(MA_OK, $this->claimStatus($m, "claim", "milestone_6"));

        $marker = $m->createPlayerMarker(PCOLOR);
        $m->tokens->moveToken($marker, "milestone_6", 1);
        $m->tokens->setTokenState("milestone_6", 1);
        $table = [];
        $m->scoreAll($table);
        $player_id = $m->getPlayerIdByColor(PCOLOR);
        $this->assertEquals(5, $table[$player_id]["details"]["milestones"]["milestone_6"]["vp"]);
        $this->assertEquals(5, $table[$player_id]["total_details"]["milestones"]);
    }

    public function testVenuphileAward() {
        $m = (new GameUT())->init(0, 0, 1);
        $m->tokens->setTokenState("tracker_tagVenus_" . PCOLOR, 2);
        $m->tokens->setTokenState("tracker_tagVenus_" . BCOLOR, 1);
        $m->clean_cache();
        $this->assertEquals(2, $m->evaluateExpression("tagVenus", PCOLOR));
        $table = [];
        $m->scoreAward("award_6", $table);
        $p = $m->getPlayerIdByColor(PCOLOR);
        $b = $m->getPlayerIdByColor(BCOLOR);
        $this->assertEquals(1, $table[$p]["details"]["awards"]["award_6"]["place"]);
        $this->assertEquals(5, $table[$p]["details"]["awards"]["award_6"]["vp"]);
        $this->assertEquals(0, $table[$b]["details"]["awards"]["award_6"]["vp"]);
    }

    public function testMaxThreeMilestonesStillEnforcedWithSix() {
        $m = (new GameUT())->init(0, 0, 1);
        $m->setTrackerValue(PCOLOR, "m", 10);
        $this->addFloaters($m, "card_main_213", 7);
        $this->assertEquals(MA_OK, $this->claimStatus($m, "claim", "milestone_6"));
        foreach ([1, 2, 3] as $num) {
            $marker = $m->createPlayerMarker(BCOLOR);
            $m->tokens->moveToken($marker, "milestone_$num", 1);
            $m->tokens->setTokenState("milestone_$num", 2);
        }
        $this->assertEquals(MA_ERR_MAXREACHED, $this->claimStatus($m, "claim", "milestone_6"));
    }

    public function testMaxThreeAwardsStillEnforcedWithSix() {
        $m = (new GameUT())->init(0, 0, 1);
        $m->setTrackerValue(PCOLOR, "m", 30);
        $this->assertEquals(MA_OK, $this->claimStatus($m, "fund", "award_6"));
        foreach ([1, 2, 3] as $num) {
            $marker = $m->createPlayerMarker(BCOLOR);
            $m->tokens->moveToken($marker, "award_$num", 1);
            $m->tokens->setTokenState("award_$num", 2);
        }
        $this->assertEquals(MA_ERR_MAXREACHED, $this->claimStatus($m, "fund", "award_6"));
    }

    // Step 4: Venus requirements and requirement modifiers

    private function venusPre(GameUT $m, int $v, string $card, string $color = PCOLOR): int {
        $m->tokens->setTokenState("tracker_v", $v);
        return $m->precondition($color, $card);
    }

    public function testVenusMinRequirement() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Neutralizer Factory");
        $this->assertEquals("card_main_240", $card);
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 8, $card));
        $this->assertEquals(MA_OK, $this->venusPre($m, 10, $card));
    }

    public function testVenusMaxRequirement() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Rotator Impacts");
        $this->assertEquals(MA_OK, $this->venusPre($m, 14, $card));
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 16, $card));
    }

    public function testAerosportNeedsFiveFloaters() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Aerosport Tournament");
        $dirigibles = $m->mtFind("name", "Dirigibles");
        $m->dbSetTokenLocation($dirigibles, "tableau_" . PCOLOR, MA_CARD_STATE_ACTION_UNUSED);
        $m->executeImmediately(PCOLOR, "res", 4, $dirigibles);
        $this->assertEquals(4, $m->evaluateExpression("resFloater", PCOLOR));
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition(PCOLOR, $card));
        $m->executeImmediately(PCOLOR, "res", 1, $dirigibles);
        $this->assertEquals(MA_OK, $m->precondition(PCOLOR, $card));
    }

    public function testAdaptationTechnologyAppliesToVenus() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_pdelta_" . PCOLOR, 2);
        $this->assertEquals(MA_OK, $this->venusPre($m, 6, "card_main_240")); // v>=10
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 4, "card_main_240"));
        $this->assertEquals(MA_OK, $this->venusPre($m, 18, "card_main_243")); // v<=14
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 20, "card_main_243"));
    }

    public function testSpecialDesignAppliesToVenus() {
        $m = $this->venusGame();
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 6, "card_main_240"));
        $m->effect_playCard(PCOLOR, $m->mtFind("name", "Special Design"));
        $m->clearEventListenerCache();
        $this->assertEquals(MA_OK, $this->venusPre($m, 6, "card_main_240"));
    }

    public function testInventrixAppliesToVenus() {
        $m = $this->venusGame();
        $m->effect_playCorporation(PCOLOR, "card_corp_6", false);
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_pdelta_" . PCOLOR));
        $this->assertEquals(MA_OK, $this->venusPre($m, 6, "card_main_240"));
    }

    public function testVenusDeltaAppliesOnlyToVenus() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_pdeltav_" . PCOLOR, 2);
        $this->assertEquals(MA_OK, $this->venusPre($m, 6, "card_main_240"));
        $eos = $m->mtFind("name", "Eos Chasma National Park"); // t>=-12
        $m->tokens->setTokenState("tracker_t", -16);
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition(PCOLOR, $eos));
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 6, "card_main_240", BCOLOR));
    }

    public function testVenusDeltaWorksForMaxReq() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Spin-Inducing Asteroid"); // v<=10
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 14, $card));
        $m->tokens->setTokenState("tracker_pdeltav_" . PCOLOR, 2);
        $this->assertEquals(MA_OK, $this->venusPre($m, 14, $card));
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 16, $card));
    }

    public function testMorningStarStacksWithAdaptationTechnology() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_pdelta_" . PCOLOR, 2);
        $m->tokens->setTokenState("tracker_pdeltav_" . PCOLOR, 2);
        $this->assertEquals(MA_OK, $this->venusPre($m, 2, "card_main_240"));
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 0, "card_main_240"));
    }

    public function testMorningStarCorpSetsDelta() {
        $m = $this->venusGame();
        $m->effect_playCorporation(PCOLOR, "card_corp_17", false);
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_pdeltav_" . PCOLOR));
        $this->assertEquals(0, $m->tokens->getTokenState("tracker_pdelta_" . PCOLOR));
    }
}
