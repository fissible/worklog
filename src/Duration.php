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

    private $DiffInterval;

    private $maximum_interval;

    public function __construct($maximum_interval = null)
    {
        $this->Base = Carbon::now();
        $this->setMaxInterval($maximum_interval);
    }

    public function setMaxInterval($maximum_interval = null)
    {
        if (! is_null($maximum_interval)) {
            switch ($maximum_interval) {
                case 'd':
                case 'days':
                    $this->maximum_interval = 'd';
                    break;
                case 'h':
                case 'hrs':
                case 'hour':
                case 'hours':
                    $this->maximum_interval = 'h';
                break;
                case 'i':
                case 'min':
                case 'mins':
                case 'minutes':
                    $this->maximum_interval = 'i';
                break;
            }
        }
    }

    public function add(\DateInterval $interval)
    {
        $this->Base->add($interval);

        return $this;
    }

    public function diff()
    {
        if (! isset($this->DiffInterval)) {
            $this->DiffInterval = Carbon::now()->diff($this->Base);
        }

        return $this->DiffInterval;
    }

    public function intervals_exceed_max()
    {
        $exceeds = false;
        switch ($this->maximum_interval) {
            case 'i':
                if ($this->diff()->h > 0) {
                    $exceeds = true;
                }
            case 'h':
                if ($this->diff()->d > 0) {
                    $exceeds = true;
                }
            case 'd':
                if ($this->diff()->m > 0) {
                    $exceeds = true;
                }
                break;
        }

        return $exceeds;
    }

    public function redistribute()
    {
        while ($this->intervals_exceed_max()) {
            if ($this->maximum_interval == 'd' || $this->maximum_interval == 'h' || $this->maximum_interval == 'i') {
                if ($this->diff()->m > 0) {
                    $this->diff()->d += $this->diff()->m * 30;
                    $this->diff()->m = 0;
                }
            }
            if ($this->maximum_interval == 'h' || $this->maximum_interval == 'i') {
                if ($this->diff()->d > 0) {
                    $this->diff()->h += $this->diff()->d * 24;
                    $this->diff()->d = 0;
                }
            }
            if ($this->maximum_interval == 'i') {
                if ($this->diff()->h > 0) {
                    $this->diff()->i += $this->diff()->h * 60;
                    $this->diff()->h = 0;
                }
            }
        }

        return $this;
    }

    public function daysString()
    {
        $this->redistribute();
        if ($DiffInterval = $this->diff()) {
            if ($DiffInterval->d) {
                return $DiffInterval->d.($DiffInterval->d == 1 ? ' day' : ' days');
            }
        }
    }

    public function hoursString()
    {
        $this->redistribute();
        if ($DiffInterval = $this->diff()) {
            if ($DiffInterval->h) {
                return $DiffInterval->h.($DiffInterval->h == 1 ? ' hour' : ' hours');
            }
        }
    }

    public function minutesString()
    {
        $this->redistribute();
        if ($DiffInterval = $this->diff()) {
            if ($DiffInterval->i) {
                return $DiffInterval->i.($DiffInterval->i == 1 ? ' min' : ' mins');
            }
        }
    }

    public function asString()
    {
        $duration = [];

        if ($days = $this->daysString()) {
            $duration[] = $days;
        }
        if ($hours = $this->hoursString()) {
            $duration[] = $hours;
        }
        if ($minutes = $this->minutesString()) {
            $duration[] = $minutes;
        }

        return implode(', ', $duration);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([ $this->Base, $name ], $arguments);
    }

    public function __toString()
    {
        return $this->asString();
    }
}
