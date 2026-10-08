<?php

declare(strict_types=1);

/**
 * All opponents draw cards into their private hands, i.e. Sponsored Academies.
 */
class Operation_draw_opp extends AbsOperation {
    function effect(string $color, int $inc): int {
        foreach ($this->game->getPlayerColors() as $other) {
            if ($other === $color) continue;
            if (!$this->game->isPlayerAlive($this->game->getPlayerIdByColor($other))) continue;
            $this->game->effect_draw($other, "deck_main", "hand_$other", $inc);
        }
        return $inc;
    }

    function isVoid(): bool {
        return false;
    }

    function canResolveAutomatically() {
        return true;
    }

    function getPrimaryArgType() {
        return '';
    }
}
