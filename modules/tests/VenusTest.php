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
}
