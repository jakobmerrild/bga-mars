<?php

declare(strict_types=1);

/**
 * Viron action: use a blue card action that has already been used this generation
 */
class Operation_reuse extends AbsOperation {
    function effect(string $color, int $inc): int {
        $tokenId = $this->getCheckedArg('target');
        $r = $this->game->getRulesFor($tokenId, 'a');
        $this->game->machine->push($r, 1, 1, $color, MACHINE_FLAG_UNIQUE, "$tokenId:a");
        $this->game->notifyMessageWithTokenName(clienttranslate('${player_name} uses action of ${token_name} again'), $tokenId, $color);
        return 1;
    }

    function argPrimaryDetails() {
        $color = $this->color;
        $map = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$color");
        $keys = array_keys($map);
        $context = $this->getContext();
        return $this->game->createArgInfo($color, $keys, function ($color, $tokenId) use ($map, $context) {
            $r = $this->game->getRulesFor($tokenId, 'a');
            if (!$r) return MA_ERR_NOTAPPLICABLE;
            if ($tokenId == $context) return MA_ERR_NOTAPPLICABLE;
            if ($map[$tokenId]['state'] != MA_CARD_STATE_ACTION_USED) return MA_ERR_NOTAPPLICABLE;
            if ($this->game->isVoidSingle($r, $color, 1, "$tokenId:a")) return MA_ERR_ACTIONCOST;
            return 0;
        });
    }

    function getPrimaryArgType() {
        return 'token';
    }
}
