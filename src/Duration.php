<?php

namespace Worklog;

use Carbon\Carbon;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/8/17
 * Time: 7:30 AM
 */
class Duration
{
    private $Base;

    public function __construct()
    {
        $this->Base = Carbon::now();
    }

    public function add(\DateInterval $interval) {
        $this->Base->add($interval);
    }

    public function calc() {
        return Carbon::now()->diff($this->Base);
    }

    public function asString() {
        $duration = '';
        $DiffInterval = $this->calc();

        if ($DiffInterval->d) {
            $duration .= $DiffInterval->d.($DiffInterval->d == 1 ? ' day' : ' days');
        }
        if ($DiffInterval->h) {
            if (mb_strlen($duration)) $duration .= ', ';
            $duration .= $DiffInterval->h.($DiffInterval->h == 1 ? ' hour' : ' hours');
        }
        if ($DiffInterval->i) {
            if (mb_strlen($duration)) $duration .= ', ';
            $duration .= $DiffInterval->i.($DiffInterval->i == 1 ? ' min' : ' mins');
        }

        return $duration;
    }

    public function __call($name, $arguments) {
        return call_user_func_array([ $this->Base, $name ], $arguments);
    }

    public function __toString() {
        return $this->asString();
    }
}