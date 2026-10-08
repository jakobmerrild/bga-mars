<?php

declare(strict_types=1);


class Operation_t extends AbsOperation {
    function effect(string $owner, int $inc): int {
        // (wgt) param: World Government Terraforming, no TR and no bonuses
        $options = $this->params() == "wgt" ? ["wgt" => true] : [];
        $this->game->effect_increaseParam($owner, $this->mnemonic, $inc, 2, $options);
        return $inc;
    }

    function getPrimaryArgType() {
        return '';
    }

    function getMax() {
        $max = $this->game->getRulesFor($this->game->getTrackerId('', $this->getMnemonic()), 'max', 0);
        return $max;
    }

    function requireConfirmation() {
        $current = $this->game->getTrackerValue('', $this->getMnemonic());
        if ($current >= $this->getMax()) {
            return true;
        }
        return false;
    }

    function getPrompt(){
        if ($this->requireConfirmation()) return clienttranslate('Temperature is already at maximum: you may proceed with this action without raising Temperature further');
        return parent::getPrompt();
    }
}
