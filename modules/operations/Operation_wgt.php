<?php

declare(strict_types=1);

// World Government Terraforming (Venus Next solar phase): raise one non-maxed global parameter, no TR and no bonuses
class Operation_wgt extends AbsOperation {
    function argPrimaryDetails() {
        $keys = ["tracker_t", "tracker_o", "tracker_w", "tracker_v"];
        return $this->game->createArgInfo($this->color, $keys, function ($color, $tracker) {
            $max = $this->game->getRulesFor($tracker, "max", 0);
            if ($this->game->tokens->getTokenState($tracker) >= $max) {
                return MA_ERR_MAXREACHED;
            }
            return MA_OK;
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
