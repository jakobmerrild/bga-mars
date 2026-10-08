<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Operation_drawTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        // Venus deck included, it has the Venus tag cards and most floater cards
        $this->game = new GameUT();
        $this->game->init(0, 0, 1);
    }

    /** put cards on top of the main deck, first in the list is drawn first */
    private function stackDeck(array $cards) {
        $state = 1000 + count($cards);
        foreach ($cards as $card) {
            $this->game->tokens->moveToken($card, "deck_main", $state--);
        }
    }

    private function dispatch(string $op, string $color = PCOLOR) {
        $this->game->machine->interrupt();
        $this->game->push($color, $op);
        $this->game->gamestate->jumpToState(STATE_GAME_DISPATCH);
        $this->game->st_gameDispatch();
    }

    private function hasTag(string $card, string $tag): bool {
        if ($tag == "Floater") {
            return $this->game->hasFloaterIcon($card);
        }
        return in_array($tag, explode(" ", $this->game->getRulesFor($card, "tags", "")));
    }

    /** project cards in the main deck that do (or do not) match the tag, wild tags left out */
    private function deckCards(string $tag, bool $match, int $count): array {
        $res = [];
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("card_main_", "deck_main")) as $card) {
            if (strstr($this->game->getRulesFor($card, "tags", ""), "Wild")) continue;
            if ($this->hasTag($card, $tag) != $match) continue;
            $res[] = $card;
            if (count($res) == $count) break;
        }
        $this->assertCount($count, $res, "not enough cards for $tag");
        return $res;
    }

    public static function tagProvider(): array {
        $tags = ["Building", "Space", "Science", "Energy", "Earth", "Jovian", "Venus", "Plant", "Microbe", "Animal", "City", "Event", "Floater"];
        return array_combine($tags, array_map(fn($tag) => [$tag], $tags));
    }

    #[DataProvider("tagProvider")]
    public function testDrawTagTakesCountMatchingCards(string $tag) {
        $color = PCOLOR;
        [$m1, $m2, $m3] = $this->deckCards($tag, true, 3);
        [$n1, $n2, $n3, $n4] = $this->deckCards($tag, false, 4);
        $this->stackDeck([$n1, $m1, $n2, $n3, $m2, $m3, $n4]);
        $hand = $this->game->tokens->countTokensInLocation("hand_$color");
        $discard = $this->game->tokens->countTokensInLocation("discard_main");

        $this->dispatch("3draw($tag)");

        $this->assertEquals($hand + 3, $this->game->tokens->countTokensInLocation("hand_$color"));
        foreach ([$m1, $m2, $m3] as $card) {
            $this->assertEquals("hand_$color", $this->game->tokens->getTokenLocation($card), $card);
        }
        // revealed cards without the tag are discarded, nothing past the last match is revealed
        foreach ([$n1, $n2, $n3] as $card) {
            $this->assertEquals("discard_main", $this->game->tokens->getTokenLocation($card), $card);
        }
        $this->assertEquals($discard + 3, $this->game->tokens->countTokensInLocation("discard_main"));
        $this->assertEquals("deck_main", $this->game->tokens->getTokenLocation($n4));
        $this->assertEmpty($this->game->machine->getTopOperations($color), "draw is fully resolved");
    }
}
