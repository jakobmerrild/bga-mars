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
}
