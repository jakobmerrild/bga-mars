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
        // the trigger and the card's own rule (pu) have the same priority, so the player orders them
        $triggered = array_filter($m->machine->getTopOperations(PCOLOR), fn($op) => $op["type"] == "m");
        $this->assertCount(1, $triggered);
        $m->fakeUserAction(reset($triggered));
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

    // Step 5: World Government Terraforming and solo win rule

    private function wgtOps(GameUT $m): array {
        return array_values(array_filter($m->machine->getTopOperations(), fn($op) => $op["type"] == "wgt"));
    }

    private function resolveWgt(GameUT $m, string $tracker, string $color = PCOLOR, bool $dispatch = true) {
        $m->push($color, "wgt");
        $tops = $m->machine->getTopOperations($color);
        $op = reset($tops);
        $this->assertEquals("wgt", $op["type"]);
        $m->fakeUserAction($op, $tracker);
        if ($dispatch) {
            $m->st_gameDispatch();
        }
    }

    private function maxMars(GameUT $m) {
        foreach (["t", "o", "w"] as $p) {
            $m->tokens->setTokenState("tracker_$p", $m->getRulesFor("tracker_$p", "max"));
        }
    }

    private function soloGame(int $venus = 1): GameUT {
        $m = new GameUT();
        $m->_setPlayerBasicInfoFromColors([PCOLOR]);
        $m->init(0, 0, $venus);
        $this->assertTrue($m->isSolo());
        return $m;
    }

    public function testWgtQueuedOnlyWithVenus() {
        $m = $this->venusGame(0);
        $m->effect_endOfTurn();
        $this->assertCount(0, $this->wgtOps($m));

        $m = $this->venusGame();
        $starting = $m->custom_getPlayerColorById($m->getCurrentStartingPlayer());
        $m->effect_endOfTurn();
        $ops = $this->wgtOps($m);
        $this->assertCount(1, $ops);
        $this->assertEquals($starting, $ops[0]["owner"]);
    }

    public function testWgtRaisesVenusWithoutTR() {
        $m = $this->venusGame();
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->resolveWgt($m, "tracker_v");
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testWgtVenusBonusNotGiven() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 6);
        $this->resolveWgt($m, "tracker_v");
        $this->assertEquals(8, $m->tokens->getTokenState("tracker_v"));
        $draws = array_filter($m->machine->getTopOperations(), fn($op) => $op["type"] == "draw");
        $this->assertCount(0, $draws);
        $this->assertEquals(0, $m->tokens->countTokensInLocation("hand_" . PCOLOR));
    }

    public function testWgtTemperatureNoBonus() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_t", -26);
        $ph = $m->getTrackerValue(PCOLOR, "ph");
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->resolveWgt($m, "tracker_t");
        $this->assertEquals(-24, $m->tokens->getTokenState("tracker_t"));
        $this->assertEquals($ph, $m->getTrackerValue(PCOLOR, "ph"));
        $this->assertEquals($tr, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testWgtOxygenNoTemperatureBonus() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_o", 7);
        $t = $m->tokens->getTokenState("tracker_t");
        $this->resolveWgt($m, "tracker_o");
        $this->assertEquals(8, $m->tokens->getTokenState("tracker_o"));
        $this->assertEquals($t, $m->tokens->getTokenState("tracker_t"));
    }

    public function testWgtOceanNoPlacementBonus() {
        $m = $this->venusGame();
        // ocean hex with a plant bonus next to another ocean hex
        $target = null;
        $other = null;
        foreach ($m->getPlanetMap() as $hex => $info) {
            if (!isset($info["ocean"]) || strpos($m->getRulesFor($hex, "r", ""), "p") === false) {
                continue;
            }
            foreach ($m->getAdjecentHexes($hex) as $adj) {
                if ($m->getRulesFor($adj, "ocean", 0)) {
                    $target = $hex;
                    $other = $adj;
                    break 2;
                }
            }
        }
        $this->assertNotNull($target);
        $m->tokens->moveToken("tile_3_1", $other, -1);
        $m->tokens->setTokenState("tracker_w", 1);
        $m->clean_cache();

        $p = $m->getTrackerValue(PCOLOR, "p");
        $mc = $m->getTrackerValue(PCOLOR, "m");
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->resolveWgt($m, "tracker_w");
        $tops = $m->machine->getTopOperations(PCOLOR);
        $op = reset($tops);
        $this->assertEquals("w(wgt)", $op["type"]);
        $m->fakeUserAction($op, $target);
        $m->st_gameDispatch();

        $this->assertEquals(2, $m->tokens->getTokenState("tracker_w"));
        $this->assertNotNull($m->tokens->getTokenOnLocation($target));
        $this->assertEquals($p, $m->getTrackerValue(PCOLOR, "p"));
        $this->assertEquals($mc, $m->getTrackerValue(PCOLOR, "m"));
        $this->assertEquals($tr, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testWgtOceanStillTriggersPlaceOcean() {
        $m = $this->venusGame();
        $m->setListeners([
            "card_x" => ["e" => "place_ocean:2p:this:any", "owner" => BCOLOR, "key" => "card_x"],
        ]);
        $this->resolveWgt($m, "tracker_w");
        $tops = $m->machine->getTopOperations(PCOLOR);
        $op = reset($tops);
        $hex = array_key_first(array_filter($m->getPlanetMap(), fn($info) => isset($info["ocean"])));
        $m->fakeUserAction($op, $hex);
        $triggered = array_filter($m->machine->getTopOperations(), fn($op) => $op["owner"] == BCOLOR && $op["data"] == "card_x:e:card_x");
        $this->assertCount(1, $triggered);
    }

    public function testWgtSkipsMaxedParams() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 30);
        $op = $m->getOperationInstanceFromType("wgt", PCOLOR);
        $details = $op->argPrimaryDetails();
        $this->assertEquals(MA_ERR_MAXREACHED, $details["tracker_v"]["q"]);
        $this->assertEquals(MA_OK, $details["tracker_t"]["q"]);
        $this->assertFalse($op->isVoid());

        $this->maxMars($m);
        $op = $m->getOperationInstanceFromType("wgt", PCOLOR);
        $this->assertTrue($op->isVoid());
    }

    public function testWgtFiresRaiseV() {
        $m = $this->venusGame();
        $m->setListeners([
            "card_x" => ["e" => "raise_v:m:this:any", "owner" => BCOLOR, "key" => "card_x"],
        ]);
        $this->resolveWgt($m, "tracker_v", PCOLOR, false);
        $triggered = array_filter($m->machine->getTopOperations(), fn($op) => $op["owner"] == BCOLOR && $op["data"] == "card_x:e:card_x");
        $this->assertCount(1, $triggered);
    }

    public function testWgtNotRunWhenGameEnds() {
        $m = $this->venusGame();
        $this->maxMars($m);
        $m->effect_endOfTurn();
        $this->assertCount(0, $this->wgtOps($m));
        $types = array_column($m->machine->getTopOperations(), "type");
        $this->assertContains("lastforest", $types);
    }

    private function coloniesGame(int $venus): GameUT {
        $m = (new GameUT())->init(0, 1, $venus);
        $m->dbSetTokenLocation("card_colo_2", "display_colonies", 1);
        $m->dbSetTokenLocation("card_colo_3", "display_colonies", 6);
        return $m;
    }

    private function colonyLevels(GameUT $m): array {
        return array_map(fn($info) => $info["state"], $m->tokens->getTokensOfTypeInLocation("card_colo", "display_colonies"));
    }

    private function assertColonyProductionDone(array $before, array $after) {
        foreach ($after as $key => $state) {
            if ($before[$key] >= 0 && $before[$key] < 6) {
                $this->assertEquals($before[$key] + 1, $state, $key);
            }
        }
    }

    public function testWgtRunsBeforeColonyProduction() {
        $m = $this->coloniesGame(1);
        $before = $this->colonyLevels($m);
        $this->assertNotEmpty($before);
        $m->effect_endOfTurn();
        $this->assertEquals($before, $this->colonyLevels($m));

        $ops = $this->wgtOps($m);
        $this->assertCount(1, $ops);
        $m->fakeUserAction($ops[0], "tracker_t");
        $types = array_column($m->machine->getTopOperations(), "type");
        $this->assertEquals(["coloprod"], $types);
        $tops = $m->machine->getTopOperations();
        $m->executeOperationSingle(reset($tops));
        $this->assertColonyProductionDone($before, $this->colonyLevels($m));
        $types = array_column($m->machine->getTopOperations(), "type");
        $this->assertEquals(["research"], $types);
    }

    public function testColoniesOnlyProductionUnchanged() {
        $m = $this->coloniesGame(0);
        $before = $this->colonyLevels($m);
        $m->effect_endOfTurn();
        $this->assertColonyProductionDone($before, $this->colonyLevels($m));
        $types = array_column($m->machine->getTopOperations(), "type");
        $this->assertEquals(["research"], $types);
    }

    public function testSoloWgtEachGenerationBeforeLast() {
        $m = $this->soloGame();
        $m->tokens->setTokenState("tracker_gen", 13);
        $m->effect_endOfTurn();
        $ops = $this->wgtOps($m);
        $this->assertCount(1, $ops);
        $this->assertEquals(PCOLOR, $ops[0]["owner"]);
    }

    public function testSoloWgtSkippedInLastGeneration() {
        $m = $this->soloGame();
        $m->tokens->setTokenState("tracker_gen", 14);
        $m->effect_endOfTurn();
        $this->assertCount(0, $this->wgtOps($m));
        $types = array_column($m->machine->getTopOperations(), "type");
        $this->assertContains("lastforest", $types);
    }

    public function testSoloWgtNotQueuedWhenAllMaxed() {
        $m = $this->soloGame();
        $m->tokens->setTokenState("tracker_gen", 5);
        $this->maxMars($m);
        $m->tokens->setTokenState("tracker_v", 30);
        $m->effect_endOfTurn();
        $this->assertCount(0, $this->wgtOps($m));
        $types = array_column($m->machine->getTopOperations(), "type");
        $this->assertContains("research", $types);
    }

    public function testSoloVenusWinRequiresVenusMaxed() {
        $m = $this->soloGame();
        $this->maxMars($m);
        $m->tokens->setTokenState("tracker_v", 28);
        $this->assertFalse($m->isSoloTerraformingComplete());
        $m->tokens->setTokenState("tracker_v", 30);
        $this->assertTrue($m->isSoloTerraformingComplete());
    }

    public function testSoloWithoutVenusWinUnchanged() {
        $m = $this->soloGame(0);
        $this->assertFalse($m->isSoloTerraformingComplete());
        $this->maxMars($m);
        $this->assertTrue($m->isSoloTerraformingComplete());
    }

    public function testSoloGoalWithVenus() {
        $m = $this->soloGame();
        $this->maxMars($m);
        $m->tokens->setTokenState("tracker_v", 28);
        $this->assertFalse($m->isSoloGoalAchieved(PCOLOR));
        $m->tokens->setTokenState("tracker_v", 30);
        $this->assertTrue($m->isSoloGoalAchieved(PCOLOR));
    }

    public function testSoloTR63IgnoresVenus() {
        $m = $this->soloGame();
        $m->setGameStateValue("var_solo_flavour", 1);
        $m->tokens->setTokenState("tracker_tr_" . PCOLOR, 63);
        $m->tokens->setTokenState("tracker_v", 0);
        $this->assertTrue($m->isSoloGoalAchieved(PCOLOR));
        $m->tokens->setTokenState("tracker_tr_" . PCOLOR, 62);
        $this->maxMars($m);
        $m->tokens->setTokenState("tracker_v", 30);
        $this->assertFalse($m->isSoloGoalAchieved(PCOLOR));
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

    // Step 8A: project cards that only need existing operations

    /** Venus cards only: milestones and awards use r for a counter expression, not an op */
    private function venusCards(GameUT $m): array {
        return array_filter(
            $m->token_types,
            fn($info, $key) => startsWith($key, "card_") && array_get($info, "deck") == "Venus",
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** leaf operation types of a rule, e.g. "2npe,city('X')" -> ["npe", "city"] */
    private function leafOpTypes($expr): array {
        if ($expr instanceof OpExpressionTerminal) {
            $type = (string) $expr;
            if (preg_match("/^(\w+)\((.*)\)$/", $type, $matches)) {
                $type = $matches[1];
            }
            return [$type];
        }
        $res = [];
        foreach ($expr->args as $arg) {
            $res = array_merge($res, $this->leafOpTypes($arg));
        }
        return $res;
    }

    /** operation rules of a card: r and a as is, e split into the outcome of each trigger */
    private function cardRules(GameUT $m, array $info): array {
        $rules = [];
        foreach (["r", "a"] as $field) {
            $rule = array_get($info, $field, "");
            if ($rule) {
                $rules["$field $rule"] = $rule;
            }
        }
        $e = array_get($info, "e", "");
        if ($e) {
            $expr = $m->parseOpExpression($e);
            $triggers = $expr->op == ";" ? $expr->args : [$expr];
            foreach ($triggers as $trigger) {
                $this->assertEquals(":", $trigger->op, "e $e");
                $outcome = (string) $trigger->args[1];
                $rules["e $outcome"] = $outcome;
            }
        }
        return $rules;
    }

    public function testAllVenusCardsParse() {
        $m = $this->venusGame();
        $cards = $this->venusCards($m);
        $this->assertCount(49 + 5 + 1, $cards); // projects, corporations, Air Scrapping
        $dir = dirname(__DIR__) . "/operations";
        foreach ($cards as $key => $info) {
            foreach ($this->cardRules($m, $info) as $what => $rule) {
                $this->assertNotEmpty(OpExpression::arr($rule), "$key $what");
                foreach ($this->leafOpTypes($m->parseOpExpression($rule)) as $type) {
                    $class = $m->getOperationRules($type, "class", "Operation_$type");
                    // a missing class file is a fatal error in getOperationInstance, so check first
                    $this->assertFileExists("$dir/$class.php", "$key $what: unknown op '$type'");
                    $this->assertInstanceOf(AbsOperation::class, $m->getOperationInstanceFromType($type, PCOLOR), "$key $what");
                }
                $this->assertInstanceOf(AbsOperation::class, $m->getOperationInstanceFromType($rule, PCOLOR, 1, $key), "$key $what");
            }
        }
    }

    public function testAllVenusCardsHaveValidPre() {
        $m = $this->venusGame();
        foreach ($this->venusCards($m) as $key => $info) {
            $pre = array_get($info, "pre", "");
            if ($pre) {
                $this->assertContains($m->precondition(PCOLOR, $key), [MA_OK, MA_ERR_PREREQ], "$key $pre");
            }
        }
    }

    private function playVenusCard(GameUT $m, string $name, string $color = PCOLOR): string {
        $card = $m->mtFind("name", $name);
        $this->assertNotNull($card, $name);
        $m->effect_playCard($color, $card);
        $m->gamestate->jumpToState(STATE_GAME_DISPATCH);
        $m->st_gameDispatch();
        return $card;
    }

    public function testGiantSolarShade() {
        $m = $this->venusGame();
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->playVenusCard($m, "Giant Solar Shade");
        $this->assertEquals(6, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 3, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testGHGImportFromVenus() {
        $m = $this->venusGame();
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $ph = $m->getTrackerValue(PCOLOR, "ph");
        $this->playVenusCard($m, "GHG Import From Venus");
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($ph + 3, $m->getTrackerValue(PCOLOR, "ph"));
        $this->assertEquals($tr + 1, $m->getTrackerValue(PCOLOR, "tr"));
        // events do not keep their tags in play
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "tagVenus"));
    }

    public function testSulphurExportsCountsItself() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_tagVenus_" . PCOLOR, 1);
        $pm = $m->getTrackerValue(PCOLOR, "pm");
        $this->playVenusCard($m, "Sulphur Exports");
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($pm + 2, $m->getTrackerValue(PCOLOR, "pm"));
    }

    public function testGyropolisVenusAndEarth() {
        $m = $this->venusGame();
        $color = PCOLOR;
        $card = $m->mtFind("name", "Gyropolis");
        $m->setTrackerValue($color, "m", 20);
        $m->setTrackerValue($color, "pe", 1);
        $this->assertEquals(MA_ERR_MANDATORYEFFECT, $m->playability($color, $card));

        $m->setTrackerValue($color, "pe", 2);
        $m->tokens->setTokenState("tracker_tagVenus_{$color}", 2);
        $m->tokens->setTokenState("tracker_tagEarth_{$color}", 1);
        $this->assertEquals(MA_OK, $m->playability($color, $card));
        $pm = $m->getTrackerValue($color, "pm");
        $this->playVenusCard($m, "Gyropolis");
        $tops = $m->machine->getTopOperations($color);
        $op = reset($tops);
        $this->assertEquals("city", $op["type"]);
        $m->fakeUserAction($op, "hex_4_3");
        $m->st_gameDispatch();
        $this->assertEquals($pm + 3, $m->getTrackerValue($color, "pm"));
        $this->assertEquals(0, $m->getTrackerValue($color, "pe"));
        $this->assertEquals(1, $m->getTrackerValue($color, "city"));
    }

    public function testTerraformingContractNeeds25TR() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Terraforming Contract");
        $m->setTrackerValue(PCOLOR, "tr", 24);
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition(PCOLOR, $card));
        $m->setTrackerValue(PCOLOR, "tr", 25);
        $this->assertEquals(MA_OK, $m->precondition(PCOLOR, $card));
        $pm = $m->getTrackerValue(PCOLOR, "pm");
        $this->playVenusCard($m, "Terraforming Contract");
        $this->assertEquals($pm + 4, $m->getTrackerValue(PCOLOR, "pm"));
    }

    public function testLuxuryFoodsRequirementWithWild() {
        $m = $this->venusGame();
        $color = PCOLOR;
        $card = $m->mtFind("name", "Luxury Foods");
        $m->tokens->setTokenState("tracker_tagVenus_{$color}", 1);
        $m->tokens->setTokenState("tracker_tagEarth_{$color}", 1);
        $this->assertEquals(MA_ERR_PREREQ, $m->precondition($color, $card));
        $m->tokens->setTokenState("tracker_tagWild_{$color}", 1);
        $this->assertEquals(MA_OK, $m->precondition($color, $card));
    }

    public function testSpinInducingAsteroidMaxReq() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Spin-Inducing Asteroid");
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 12, $card));
        $this->assertEquals(MA_OK, $this->venusPre($m, 10, $card));
        $this->playVenusCard($m, "Spin-Inducing Asteroid");
        $this->assertEquals(14, $m->tokens->getTokenState("tracker_v"));
    }

    public function testNeutralizerFactoryAtVenusBonus() {
        $m = $this->venusGame();
        $card = $m->mtFind("name", "Neutralizer Factory");
        $this->assertEquals(MA_ERR_PREREQ, $this->venusPre($m, 6, $card));
        $this->assertEquals(MA_OK, $this->venusPre($m, 14, $card));
        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $this->playVenusCard($m, "Neutralizer Factory");
        $this->assertEquals(16, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 2, $m->getTrackerValue(PCOLOR, "tr"));
    }

    public function testSimpleVenusCardsVp() {
        $m = $this->venusGame();
        $expected = [
            "Atalanta Planitia Lab" => 2,
            "Luxury Foods" => 2,
            "Solarnet" => 1,
            "Terraforming Contract" => 0,
        ];
        foreach ($expected as $name => $vp) {
            $this->assertEquals($vp, $m->getRulesFor($m->mtFind("name", $name), "vp"), $name);
        }
    }

    // Step 8B: project cards with floaters, microbes, animals and asteroids

    /** put a card on the tableau without playing its rules */
    private function onTableau(GameUT $m, string $name, string $color = PCOLOR): string {
        $card = $m->mtFind("name", $name);
        $this->assertNotNull($card, $name);
        $this->addFloaters($m, $card, 0, $color);
        return $card;
    }


    private function runCardAction(GameUT $m, string $card, string $color = PCOLOR) {
        $m->machine->interrupt(); // ahead of whatever turn is on the stack
        $m->push($color, $m->getRulesFor($card, "a"), $card);
        $m->gamestate->jumpToState(STATE_GAME_DISPATCH);
        $m->st_gameDispatch();
    }

    private function topOpTypes(GameUT $m, string $color = PCOLOR): array {
        return array_values(array_map(fn($op) => $op["type"], $m->machine->getTopOperations($color)));
    }

    /** resolve the single top operation of the given type, with an optional target */
    private function choose(GameUT $m, string $type, $target = null, ?int $count = null, string $color = PCOLOR) {
        $found = array_filter($m->machine->getTopOperations($color), fn($op) => $op["type"] == $type);
        $this->assertCount(1, $found, "$type in " . json_encode($this->topOpTypes($m, $color)));
        $m->fakeUserAction(reset($found), $target, false, $count);
        $m->st_gameDispatch();
    }

    private function targets(GameUT $m, string $type, string $context, string $color = PCOLOR): array {
        $op = $m->getOperationInstanceFromType($type, $color, 1, $context);
        return array_keys($op->argPrimaryDetails());
    }

    private function resOn(GameUT $m, string $card): int {
        return $m->tokens->countTokensInLocation($card);
    }

    private function cardVp(GameUT $m, string $card, string $color = PCOLOR) {
        return $m->evaluateExpression($m->getRulesFor($card, "vp"), $color, $card);
    }

    public function testAirScrappingExpeditionOnlyVenusTargets() {
        $m = (new GameUT())->init(0, 1, 1);
        $this->onTableau($m, "Jupiter Floating Station");
        $dirigibles = $this->onTableau($m, "Dirigibles");
        $card = $m->mtFind("name", "Air-Scrapping Expedition");
        $this->assertEquals([$dirigibles], $this->targets($m, "ores(Floater,Venus)", $card));

        $this->playVenusCard($m, "Air-Scrapping Expedition");
        $this->choose($m, "ores(Floater,Venus)", $dirigibles);
        $this->assertEquals(3, $this->resOn($m, $dirigibles));
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
    }

    public function testAtmoscoopChoiceTempOrVenus() {
        $m = $this->venusGame();
        $dirigibles = $this->onTableau($m, "Dirigibles");
        $this->playVenusCard($m, "Atmoscoop");
        $types = $this->topOpTypes($m);
        $this->assertContains("2t", $types);
        $this->assertContains("2v", $types);
        $this->choose($m, "2v");
        $this->choose($m, "ores(Floater)", $dirigibles);
        $this->assertEquals(4, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals(2, $this->resOn($m, $dirigibles));
    }

    public function testExtractorBalloonsSpend2Floaters() {
        $m = $this->venusGame();
        $card = $this->playVenusCard($m, "Extractor Balloons");
        $this->assertEquals(3, $this->resOn($m, $card));
        $this->runCardAction($m, $card);
        $this->choose($m, "2nres:v");
        $this->assertEquals(1, $this->resOn($m, $card));
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
    }

    public function testJetStreamTitaniumForFloaters() {
        $m = $this->venusGame();
        $card = $this->onTableau($m, "Jet Stream Microscrappers");
        $m->setTrackerValue(PCOLOR, "u", 1);
        $this->runCardAction($m, $card); // no floaters to spend: titanium option is the only one
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "u"));
        $this->assertEquals(2, $this->resOn($m, $card));
    }

    public function testRotatorImpactsPayWithTitanium() {
        $m = $this->venusGame();
        $card = $this->onTableau($m, "Rotator Impacts");
        $m->setTrackerValue(PCOLOR, "m", 10);
        $m->setTrackerValue(PCOLOR, "u", 2);
        $this->runCardAction($m, $card);
        $this->choose($m, "nmu", "2u");
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "u"));
        $this->assertEquals(10, $m->getTrackerValue(PCOLOR, "m"));
        $this->assertEquals(1, $this->resOn($m, $card));
        $this->assertEquals("Asteroid", $m->getTokenName("tagAsteroid"));

        $this->runCardAction($m, $card);
        $this->choose($m, "nres:v");
        $this->assertEquals(0, $this->resOn($m, $card));
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
    }

    public function testStratosphericBirdsRequiresFloater() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 12);
        $m->setTrackerValue(PCOLOR, "m", 20);
        $birds = $m->mtFind("name", "Stratospheric Birds");
        $dirigibles = $this->onTableau($m, "Dirigibles");
        $this->assertNotEquals(MA_OK, $m->playability(PCOLOR, $birds));

        $this->addFloaters($m, $dirigibles, 1);
        $this->assertEquals(MA_OK, $m->playability(PCOLOR, $birds));
        $this->playVenusCard($m, "Stratospheric Birds"); // the only floater card is chosen automatically
        $this->assertEquals(0, $this->resOn($m, $dirigibles));
        $this->assertEquals(0, $this->resOn($m, $birds));
    }

    public function testSulphurEatingBacteriaTripleMC() {
        $m = $this->venusGame();
        $card = $this->onTableau($m, "Sulphur-Eating Bacteria");
        $this->addFloaters($m, $card, 3);
        $before = $m->getTrackerValue(PCOLOR, "m");
        $this->runCardAction($m, $card);
        $this->choose($m, "counter(resCard,1):nres,m,m,m");
        $this->choose($m, "nres,m,m,m", null, 3);
        $this->assertEquals($before + 9, $m->getTrackerValue(PCOLOR, "m"));
        $this->assertEquals(0, $this->resOn($m, $card));
    }

    public function testCorroderSuitsAnyResourceVenusCard() {
        $m = $this->venusGame();
        $birds = $this->onTableau($m, "Stratospheric Birds");
        $this->onTableau($m, "Regolith Eaters"); // microbe card without a Venus tag
        $pm = $m->getTrackerValue(PCOLOR, "pm");
        $card = $m->mtFind("name", "Corroder Suits");
        $this->assertEquals([$birds], $this->targets($m, "ores(Any,Venus)", $card));
        $this->playVenusCard($m, "Corroder Suits");
        $this->choose($m, "ores(Any,Venus)", $birds);
        $this->assertEquals(1, $this->resOn($m, $birds));
        $this->assertEquals(1, $m->evaluateExpression("resCard", PCOLOR, $birds));
        $this->assertEquals($pm + 2, $m->getTrackerValue(PCOLOR, "pm"));
    }

    public function testMaxwellBaseNotItself() {
        $m = $this->venusGame();
        $maxwell = $this->onTableau($m, "Maxwell Base");
        $insects = $this->onTableau($m, "Venusian Insects");
        $targets = $this->targets($m, $m->getRulesFor($maxwell, "a"), $maxwell);
        $this->assertNotContains($maxwell, $targets);
        $this->assertEquals([$insects], $targets);
    }

    public function testHydrogenToVenusPerJovian() {
        $m = $this->venusGame();
        $dirigibles = $this->onTableau($m, "Dirigibles");
        $m->tokens->setTokenState("tracker_tagJovian_" . PCOLOR, 2);
        $this->playVenusCard($m, "Hydrogen to Venus");
        $this->choose($m, "ores(Floater,Venus)", $dirigibles);
        $this->assertEquals(2, $this->resOn($m, $dirigibles));
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
    }

    public function testFloatingHabsVp() {
        $m = $this->venusGame();
        $card = $this->onTableau($m, "Floating Habs");
        $this->addFloaters($m, $card, 5);
        $this->assertEquals(2, $this->cardVp($m, $card));
    }

    public function testStratopolisVp() {
        $m = $this->venusGame();
        $card = $this->onTableau($m, "Stratopolis");
        $this->addFloaters($m, $card, 7);
        $this->assertEquals(2, $this->cardVp($m, $card));
    }

    public function testVenusFloaterCardWithColoniesFloaterTarget() {
        $m = (new GameUT())->init(0, 1, 1);
        $mappers = $this->onTableau($m, "Aerial Mappers");
        $titan = $this->onTableau($m, "Titan Air-Scrapping");
        $targets = $this->targets($m, "ores(Floater)", $mappers);
        $this->assertContains($titan, $targets);
        $this->assertContains($mappers, $targets); // ANY card includes this one
    }

    public function testFreyjaBiodomesNeedsEnergyProduction() {
        $m = $this->venusGame();
        $m->tokens->setTokenState("tracker_v", 10);
        $m->setTrackerValue(PCOLOR, "m", 20);
        $card = $m->mtFind("name", "Freyja Biodomes");
        $this->assertEquals(MA_ERR_MANDATORYEFFECT, $m->playability(PCOLOR, $card));
        $m->setTrackerValue(PCOLOR, "pe", 1);
        $this->assertEquals(MA_OK, $m->playability(PCOLOR, $card));

        $birds = $this->onTableau($m, "Stratospheric Birds");
        $this->onTableau($m, "Regolith Eaters"); // microbe card without a Venus tag
        $pm = $m->getTrackerValue(PCOLOR, "pm");
        $this->playVenusCard($m, "Freyja Biodomes");
        $this->assertEquals([$birds], $this->targets($m, "ores(Animal,Venus)", $card));
        $this->assertEquals(["none"], $this->targets($m, "ores(Microbe,Venus)", $card));
        $this->choose($m, "2ores(Animal,Venus)", $birds);
        $this->assertEquals(2, $this->resOn($m, $birds));
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "pe"));
        $this->assertEquals($pm + 2, $m->getTrackerValue(PCOLOR, "pm"));
    }

    public function testVenusSoilsAnotherCard() {
        $m = $this->venusGame();
        $insects = $this->onTableau($m, "Venusian Insects");
        $pp = $m->getTrackerValue(PCOLOR, "pp");
        $soils = $this->playVenusCard($m, "Venus Soils");
        $this->choose($m, "ores(Microbe)", $insects);
        $this->assertEquals(2, $this->resOn($m, $insects));
        $this->assertEquals(0, $this->resOn($m, $soils));
        $this->assertEquals($pp + 1, $m->getTrackerValue(PCOLOR, "pp"));
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
    }

    // Step 8C: cards with special rules

    private function topOp(GameUT $m, string $color = PCOLOR) {
        $tops = $m->machine->getTopOperations($color);
        return reset($tops);
    }

    private function playAndDispatch(GameUT $m, string $card, string $color = PCOLOR) {
        $m->effect_playCard($color, $card);
        $m->gamestate->jumpToState(STATE_GAME_DISPATCH);
        $m->st_gameDispatch();
    }

    private function resolveTop(GameUT $m, $target = null, string $color = PCOLOR) {
        $op = $this->topOp($m, $color);
        $this->assertNotFalse($op, "no pending operation");
        $m->fakeUserAction($op, $target);
        $m->st_gameDispatch();
    }

    private function handCount(GameUT $m, string $color): int {
        return count($m->tokens->getTokensInLocation("hand_$color"));
    }

    private function addResources(GameUT $m, string $card, int $count, string $color = PCOLOR) {
        for ($i = 0; $i < $count; $i++) {
            $res = $m->createPlayerResource($color);
            $m->dbSetTokenLocation($res, $card, 0);
        }
    }

    private function paymentTargets(GameUT $m, string $card, string $color = PCOLOR): array {
        $payment = $m->getPayment($color, $card);
        $args = $m->debug_opInfo($payment, $card);
        return $args["args"]["target"];
    }

    public function testCometForVenusTargetsOnlyVenusTagPlayers() {
        $m = $this->venusGame();
        $comet = "card_main_218";
        $m->setTrackerValue(BCOLOR, "m", 3);
        $op = $m->getOperationInstanceFromType("nm_Any(Venus)", PCOLOR, 1, $comet);
        $info = $op->argPrimaryDetails();
        $this->assertNotEquals(MA_OK, $info[BCOLOR]["q"]);

        $m->tokens->setTokenState("tracker_tagVenus_" . BCOLOR, 1);
        $op = $m->getOperationInstanceFromType("nm_Any(Venus)", PCOLOR, 1, $comet);
        $info = $op->argPrimaryDetails();
        $this->assertEquals(MA_OK, $info[BCOLOR]["q"]);

        $v = $m->tokens->getTokenState("tracker_v");
        $this->playAndDispatch($m, $comet);
        $this->assertEquals($v + 2, $m->tokens->getTokenState("tracker_v"));
        $this->resolveTop($m, BCOLOR);
        $this->assertEquals(0, $m->getTrackerValue(BCOLOR, "m"));
    }

    public function testCometForVenusEventTagDoesNotCount() {
        $m = $this->venusGame();
        // a played Venus event (GHG Import From Venus) leaves no Venus tag in play
        $m->effect_playCard(BCOLOR, "card_main_228");
        $m->setTrackerValue(BCOLOR, "m", 5);
        $op = $m->getOperationInstanceFromType("nm_Any(Venus)", PCOLOR, 1, "card_main_218");
        $info = $op->argPrimaryDetails();
        $this->assertNotEquals(MA_OK, $info[BCOLOR]["q"]);
    }

    public function testDirigiblesPayVenusCard() {
        $m = $this->venusGame();
        $dirigibles = "card_main_222";
        $this->assertEquals("ores(Floater)", $m->getRulesFor($dirigibles, "a"));
        $m->effect_playCard(PCOLOR, $dirigibles);
        $this->addResources($m, $dirigibles, 2);
        $m->setTrackerValue(PCOLOR, "m", 30);

        $card = "card_main_223"; // Extractor Balloons, Venus, 21
        $this->assertContains("2resFloater15m", $this->paymentTargets($m, $card));

        $targets = $this->paymentTargets($m, "card_main_232"); // Io Sulphur Research, no Venus tag
        foreach ($targets as $target) {
            $this->assertStringNotContainsString("resFloater", $target);
        }

        $m->push(PCOLOR, $m->getPayment(PCOLOR, $card), $card);
        $this->resolveTop($m, "2resFloater15m");
        $this->assertEquals(0, $m->tokens->countTokensInLocation($dirigibles));
        $this->assertEquals(15, $m->getTrackerValue(PCOLOR, "m"));
    }

    public function testVenusWaystationDiscount() {
        $m = $this->venusGame();
        $this->assertEquals("21nm", $m->getPayment(PCOLOR, "card_main_223")); // Extractor Balloons
        $m->effect_playCard(PCOLOR, "card_main_258");
        $this->assertEquals("19nm", $m->getPayment(PCOLOR, "card_main_223"));
        $this->assertEquals("17nm", $m->getPayment(PCOLOR, "card_main_232")); // Io Sulphur Research
    }

    public function testVenusianAnimalsScienceTrigger() {
        $m = $this->venusGame();
        $animals = "card_main_259";
        $this->playAndDispatch($m, $animals);
        $this->assertEquals(1, $m->tokens->countTokensInLocation($animals));
        $this->playAndDispatch($m, $m->mtFindByName("Physics Complex")); // one Science tag
        $this->resolveTop($m); // confirm adding the animal
        $this->assertEquals(2, $m->tokens->countTokensInLocation($animals));
        $this->playAndDispatch($m, "card_main_233"); // Ishtar Mining, no Science tag
        $this->assertEquals(2, $m->tokens->countTokensInLocation($animals));
    }

    public function testSponsoredAcademiesOpponentsDraw() {
        $m = $this->venusGame();
        $academies = "card_main_247";
        $m->dbSetTokenLocation($academies, "hand_" . PCOLOR, 0);
        $m->dbSetTokenLocation("card_main_1", "hand_" . PCOLOR, 0);
        $m->dbSetTokenLocation("card_main_2", "hand_" . PCOLOR, 0);
        $bhand = $this->handCount($m, BCOLOR);
        $m->setTrackerValue(PCOLOR, "m", 20);
        $this->assertEquals(MA_OK, $m->playability(PCOLOR, $academies));

        $this->playAndDispatch($m, $academies);
        $this->resolveTop($m, "card_main_1"); // discard
        for ($i = 0; $i < 5 && $this->topOp($m); $i++) {
            $this->resolveTop($m); // confirmations
        }
        $this->assertEquals("discard_main", $m->tokens->getTokenLocation("card_main_1"));
        $this->assertEquals(4, $this->handCount($m, PCOLOR)); // card_main_2 + 3 drawn
        $this->assertEquals($bhand + 1, $this->handCount($m, BCOLOR));
    }

    public function testSponsoredAcademiesNeedsCardToDiscard() {
        $m = $this->venusGame();
        $academies = "card_main_247";
        $m->dbSetTokenLocation($academies, "hand_" . PCOLOR, 0);
        $m->setTrackerValue(PCOLOR, "m", 20);
        $this->assertEquals(MA_ERR_MANDATORYEFFECT, $m->playability(PCOLOR, $academies));
        $m->dbSetTokenLocation("card_main_1", "hand_" . PCOLOR, 0);
        $this->assertEquals(MA_OK, $m->playability(PCOLOR, $academies));
    }

    public function testIoSulphurResearchDraw1Or3() {
        foreach ([2 => 1, 3 => 3] as $tags => $draw) {
            $m = $this->venusGame();
            $m->tokens->setTokenState("tracker_tagVenus_" . PCOLOR, $tags);
            $hand = $this->handCount($m, PCOLOR);
            $this->playAndDispatch($m, "card_main_232");
            for ($i = 0; $i < 3 && $this->topOp($m); $i++) {
                $this->resolveTop($m); // draw confirmation
            }
            $this->assertEquals($hand + $draw, $this->handCount($m, PCOLOR), "$tags Venus tags");
        }
    }

    public function testVenusMagnetizer() {
        $m = $this->venusGame();
        $card = "card_main_256";
        $a = $m->getRulesFor($card, "a");
        $this->assertTrue($m->isVoidSingle($a, PCOLOR, 1, $card));
        $m->setTrackerValue(PCOLOR, "pe", 1);
        $this->assertFalse($m->isVoidSingle($a, PCOLOR, 1, $card));

        $tr = $m->getTrackerValue(PCOLOR, "tr");
        $m->push(PCOLOR, $a, "$card:a");
        $m->st_gameDispatch();
        $this->assertEquals(0, $m->getTrackerValue(PCOLOR, "pe"));
        $this->assertEquals(2, $m->tokens->getTokenState("tracker_v"));
        $this->assertEquals($tr + 1, $m->getTrackerValue(PCOLOR, "tr"));
    }
}
