<?php

declare(strict_types=1);

// World Government Terraforming (Venus Next solar phase): raise one non-maxed global parameter, no TR and
// only the track bonuses that raise another parameter (O2 8% -> temperature, temperature 0 -> ocean)
class Operation_wgt extends AbsOperation {
    function argPrimaryDetails() {
        // button labels make clear the active player chooses on behalf of the World Government
        $names = [
            "tracker_t" => clienttranslate("World Government: raise Temperature"),
            "tracker_o" => clienttranslate("World Government: raise Oxygen"),
            "tracker_w" => clienttranslate("World Government: raise Ocean"),
            "tracker_v" => clienttranslate("World Government: raise Venus"),
        ];
        return $this->game->createArgInfo($this->color, array_keys($names), function ($color, $tracker) use ($names) {
            $max = $this->game->getRulesFor($tracker, "max", 0);
            if ($this->game->tokens->getTokenState($tracker) >= $max) {
                return ["q" => MA_ERR_MAXREACHED, "name" => $names[$tracker]];
            }
            return ["q" => MA_OK, "name" => $names[$tracker]];
        });
    }

    function getPrimaryArgType() {
        return "token";
    }

    function effect(string $owner, int $inc): int {
        $tracker = $this->getCheckedArg("target");
        $type = getPart($tracker, 1);
        if ($type == "w") {
            // the player chooses where the ocean goes
            $this->game->push($owner, "w(wgt)");
        } else {
            $this->game->effect_increaseParam($owner, $type, 1, $type == "o" ? 1 : 2, ["wgt" => true]);
        }
        return 1;
    }
}
