<?php

declare(strict_types=1);

class Operation_counter extends AbsOperation {

    function isVoid(): bool {
        return false;
    }

    function evaluate() {

        $owner = $this->getOwner();
        $expr = $this->getParam(0);
        $min = $this->getParam(1, '');
        $max = $this->getParam(2, '');
        if ($min === 'null') $min = '';

        $count = $this->evaluateTags(trim($expr), $owner);
        if (!is_numeric($count))  throw new Exception("Did not evaluate to a number $expr $count");

        if ($max) {
            $maxcount = (int) $max;
            if ($count > $maxcount) $count = $maxcount;
        }

        $mincount = $min  ? $this->evaluateTags(trim($min), $owner) : $count;
        if (!is_numeric($mincount))  throw new Exception("Did not evaluate to a number $min $mincount");


        return [$count, $mincount];
    }

    function evaluateTags(string $expr, string $owner) {
        preg_match_all('/tag[A-Z]\w*/', $expr, $matches);
        $tags = array_unique($matches[0]);
        if (count($tags) > 1 && !in_array('tagWild', $tags)) {
            // sum of several tag types (Gyropolis: Venus and Earth tags), each wild tag counts as only one of them,
            // so add wilds once instead of to each term
            return $this->game->evaluateExpression("($expr)+tagWild", $owner, $this->getContext(), ['wilds' => null]);
        }
        return $this->game->evaluateExpression($expr, $owner, $this->getContext(), ['wilds' => []]);
    }

    function effect(string $owner, int $inc): int {
        // counter function, followed by expression
        // result of experssion is set as counter for top rank operation
        list($count, $mincount) = $this->evaluate();
        //$this->game->debugLog("-evaluted to $count:$mincount");
        $this->game->machine->hide($this->op_info); // this cannot be part of top
        $tops = $this->game->machine->getTopOperations($owner);
        $top = array_shift($tops);
        $this->game->machine->setCount($top, $count, $mincount);
        return 1;
    }

    function getPrimaryArgType() {
        return '';
    }
}
