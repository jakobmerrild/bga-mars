<?php

declare(strict_types=1);

// place an abstract tile
abstract class AbsOperationTile extends AbsOperation {
    protected $tile_id = null;

    function argPrimaryDetails() {
        $color = $this->color;
        $map = $this->getPlanetMap();
        $keys = array_keys($map);
        return $this->game->createArgInfo($color, $keys, function ($color, $hex) use ($map) {
            $info = $map[$hex];
            $rc = $this->checkOccupied($info);
            if ($rc) {
                return $rc;
            }
            return $this->checkPlacement($color, $hex, $info, $map);
        });
    }

    function getPrimaryArgType() {
        return "token";
    }

    function arg() {
        $result = parent::arg();
        $result["object"] = $this->getTileId();
        return $result;
    }

    function checkOccupied($info) {
        $color = $this->color;
        if (array_key_exists("tile", $info)) {
            return MA_ERR_OCCUPIED;
        }
        $claimer = array_get($info, "claimed");
        if ($claimer && $claimer !== $color) {
            return MA_ERR_RESERVED;
        }
        return MA_OK;
    }

    protected function getPlanetMap() {
        return $this->game->getPlanetMap();
    }

    protected function getTileId() {
        if ($this->tile_id != null) {
            return $this->tile_id;
        }
        //$this->game->prof_point("tileid","start");
        $type = $this->getTileType();
        $tile = $this->game->tokens->getTokenOfTypeInLocation("tile_{$type}_", null, 0);
        if (!$tile) {
            // XXX can be removed later
            $count = count($this->game->tokens->getTokensOfTypeInLocation("tile_{$type}_"));
            $count += 1;
            $this->game->tokens->createToken("tile_{$type}_$count");
            $tile = $this->game->tokens->getTokenOfTypeInLocation("tile_{$type}_", null, 0);
        }
        if (!$tile) {
            throw new BgaSystemException("Cannot find tile of type $type");
        }
        $this->tile_id = $tile["key"];
        //$this->game->prof_point("tileid","end");
        return $this->tile_id;
    }

    protected function getReservedArea(): ?string {
        return $this->params();
    }

    protected function isAdjecentHexesOfType($what, $towhat = 0, $ownwer = null) {
        return count($this->getAdjecentHexesOfType($what, $towhat, $ownwer)) > 0;
    }

    protected function getAdjecentHexesOfType($what, $towhat = 0, $ownwer = null) {
        return $this->game->getAdjecentHexesOfType($what, $towhat, $ownwer);
    }

    protected function checkAdjRulesPasses($ohex, $color, $rule) {
        if (!$rule) {
            return true;
        }
        $count = $this->game->evaluateAdj($color, $ohex, $rule);
        if (!$count) {
            return false;
        }
        return true;
    }

    protected function findReservedAreas($names): array {
        $res = [];
        if (!$names) {
            return $res;
        }
        $map = $this->getPlanetMap();
        $names_arr = explode(",", $names);
        foreach ($names_arr as $name) {
            $nname = trim($name);
            if (!$nname) {
                continue;
            }
            foreach ($map as $hex => $hexinfo) {
                if (array_get($hexinfo, "name") === $nname) {
                    $res[] = $hex;
                    break;
                }
            }
        }
        return $res;
    }

    function checkPlacement($color, $location, $info, $planetmap) {
        $tile = $this->getStateArg("object");
        $tt = $this->game->getRulesFor($tile, "tt");

        if ($tt == MA_TILE_CITY) {
            // have to do it here because city operation is not only one who can do city
            $ret = $this->checkCityPlacement($color, $location, $info, $planetmap);
            if ($ret) {
                return $ret;
            }
        }

        if (isset($info["ocean"])) {
            return MA_ERR_RESERVED;
        }
        // now check if can afford the tile "bonus" payment
        if ($this->checkMandatoryEffect($color, $location)) {
            return MA_ERR_MANDATORYEFFECT;
        }
        return 0;
    }

    function checkMandatoryEffect($color, $location) {
        $rules = $this->game->getRulesFor($location, "r");
        if ($rules) {
            $op = $this->game->getOperationInstanceFromType("$rules", $color, 1);
            if ($op->isVoid()) {
                // its void because of ME payment however ocean rebates gained during placement can compensate
                $rebate = $this->getPotentialOceanRebate($location, $rules);
                if ($rebate > 0 && $this->canPayWithRebate($color, $rules, $rebate)) {
                    return MA_OK;
                }
                return MA_ERR_MANDATORYEFFECT;
            }
        }
        return 0;
    }

    /**
     * M€ that can be gained from ocean adjacency while resolving placement on $location:
     * oceans adjacent to the hex itself, plus best placement of an ocean granted by the hex bonus,
     * directly (i.e. Hellas South Pole) or via temperature bonus (i.e. Vastitas Borealis center)
     */
    function getPotentialOceanRebate($location, $rules) {
        $rebate = 2 * count($this->getAdjecentHexesOfType($location, MA_TILE_OCEAN));
        $expr = $this->game->parseOpExpression($rules);
        $args = $expr->op == "!" ? [$rules] : array_map(fn($arg) => OpExpression::str($arg), $expr->args);
        if (!in_array("w", $args) && !(in_array("t", $args) && $this->getNextTemperatureBonus() == "w")) {
            return $rebate;
        }
        $max = $this->game->getRulesFor($this->game->getTrackerId("", "w"), "max", 0);
        if ($this->game->getTrackerValue("", "w") >= $max) {
            return $rebate;
        }
        $best = 0;
        foreach ($this->getPlanetMap() as $hex => $info) {
            if ($hex === $location || !isset($info["ocean"]) || $this->checkOccupied($info)) {
                continue;
            }
            $best = max($best, count($this->getAdjecentHexesOfType($hex, MA_TILE_OCEAN)));
        }
        return $rebate + 2 * $best;
    }

    function getNextTemperatureBonus() {
        $current = $this->game->getTrackerValue("", "t");
        $max = $this->game->getRulesFor($this->game->getTrackerId("", "t"), "max", 0);
        if ($current >= $max) {
            return "";
        }
        $v = $current + 2;
        $nvalue = $v >= 0 ? $v : "n" . -$v;
        return $this->game->getRulesFor("param_t_{$nvalue}", "r", "");
    }

    function canPayWithRebate($color, $rules, $rebate) {
        $expr = $this->game->parseOpExpression($rules);
        $args = $expr->op == "!" ? [$rules] : array_map(fn($arg) => OpExpression::str($arg), $expr->args);
        foreach ($args as $arg) {
            $matches = null;
            if (!preg_match("/^(\d+)nm$/", $arg, $matches)) {
                continue;
            }
            $cost = (int) $matches[1] - $rebate;
            if ($cost > 0 && $this->game->getOperationInstanceFromType("{$cost}nm", $color, 1)->isVoid()) {
                return false;
            }
        }
        return true;
    }

    function checkCityPlacement($color, $ohex, $info, $map) {
        if (isset($info["ocean"])) {
            return MA_ERR_RESERVED;
        }
        $reservename = $this->getReservedArea();
        if ($reservename == "vol") {
            if ($this->game->getMapNumber() != MA_OPTVALUE_MAP_HELLAS) {
                if (!isset($info["vol"])) {
                    return MA_ERR_NOTRESERVED;
                } else {
                    return MA_OK;
                }
            }
            $reservename = "";
        }
        if (!$reservename) {
            if (isset($info["reserved"])) {
                return MA_ERR_RESERVED;
            }
            $others = count($this->getAdjecentHexesOfType($ohex, MA_TILE_CITY));
            if ($others > 0) {
                return MA_ERR_CITYPLACEMENT;
            }
        } else {
            $reshexes = $this->findReservedAreas($reservename);
            if (count($reshexes) == 0) {
                // if reserved area not on the map - generic rules apply
                if (isset($info["reserved"])) {
                    return MA_ERR_RESERVED;
                }
                if ($this->checkAdjRulesPasses($ohex, $color, $reservename)) {
                    return MA_OK;
                }
                return MA_ERR_CITYPLACEMENT;
            }
            if (array_search($ohex, $reshexes) === false) {
                return MA_ERR_NOTRESERVED;
            }
        }
        return 0;
    }

    abstract function getTileType(): int;

    function effect_placeTile() {
        $hex = $this->getCheckedArg("target");
        $object = $this->getStateArg("object");
        $owner = $this->getOwner();

        $tile = $this->game->effect_placeTile($owner, $object, $hex);
        $tt = $this->game->getRulesFor($tile, "tt");

        if ($tt == MA_TILE_CITY) {
            // the effect triggered here because its not only "city" operation that can place cities
            $this->game->incTrackerValue($owner, "city");
            $this->game->triggerEffect($owner, "place_city", $tile);

            if (!$this->game->getRulesFor($hex, "inspace")) {
                $this->game->incTrackerValue($owner, "cityonmars");
                $this->game->triggerEffect($owner, "place_cityonmars", $tile);
            }
        }

        return $object;
    }

    function canFail() {
        return true;
    }
}
