<?php

declare(strict_types=1);

// rest of the solar phase (colony production, first player marker, research), used when it has to wait
// for World Government Terraforming
class Operation_endgen extends AbsOperation {
    function effect(string $owner, int $inc): int {
        $this->game->effect_endOfGeneration();
        return 1;
    }

    function getPrimaryArgType() {
        return "";
    }
}
