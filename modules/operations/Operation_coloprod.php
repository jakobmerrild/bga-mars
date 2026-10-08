<?php

declare(strict_types=1);

// colony production step of the solar phase, used when it has to wait for World Government Terraforming
class Operation_coloprod extends AbsOperation {
    function effect(string $owner, int $inc): int {
        $this->game->effect_colonyProduction();
        return 1;
    }

    function getPrimaryArgType() {
        return "";
    }
}
