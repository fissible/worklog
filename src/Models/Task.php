<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/11/17
 * Time: 2:52 PM
 */

namespace Worklog\Models;


use Carbon\Carbon;
use Worklog\Str;

class Task extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var array
     */
    protected $dates = ['date'];

    /**
     * @var array
     */
    protected $fillable = ['issue', 'description', 'date', 'start', 'stop'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;


    protected static $fields = [
        'id' => [
            'type' => 'integer',
            'auto_increment' => true
        ],
        'issue' => [
            'type' => 'string',
            'default' => null,
            'prompt' => 'What is the JIRA Issue key?',
            'required' => false
        ],
        'description' => [
            'type' => 'string',
            'default' => null,
            'prompt' => 'What did you do?',
            'required' => true
        ],
        'date' => [
            'type' => 'string',
            'default' => '*Str::datetime',
            'prompt' => 'What was the date (YYYY-MM-DD)?',
            'required' => true
        ],
        'start' => [
            'type' => 'string',
            'default' => '*DateTime->format(\'H:i\')',
            'prompt' => 'What time did you start?',
            'required' => false
        ],
        'stop' => [
            'type' => 'string',
            'default' => '*DateTime->format(\'H:i\')',
            'prompt' => 'What time did you stop?',
            'required' => false
        ]
    ];

    protected static $primary_keys = [ 'id' ];

    protected static $display_headers = [
        'id' => 'ID',
        'issue' => 'Issue',
        'description' => 'Description',
        'date_string' => 'Date',
        'start_string' => 'Start',
        'stop_string' => 'Stop',
        'duration_string' => 'Time Spent'
    ];

    private static $exception_strings = [
        'time_format' => 'Start/stop times must be a time format: HH:MM'
    ];

    const CACHE_TAG = 'start';



    /**
     * Scopes
     */

    /**
     * @param $query
     * @return mixed
     */
    public function scopeDefaultSort($query) {
        return $query->orderBy('date', 'desc')->orderBy('start', 'desc');
    }



    /**
     * Getters
     */

    /**
     * @return Carbon
     */
    public function getDateAttribute() {
        return Carbon::parse($this->attributes['date']);
        //return $this->attributes['date']; // raw value
    }

    /**
     * @return mixed|string
     */
    public function getDescriptionAttribute() {
        $str = '';
        if ($this->hasAttribute('description')) {
            $str = str_replace('\n', "\n", $this->attributes['description']);
        }

        return $str;
    }

    /**
     * @return mixed|string
     */
    public function getDescriptionSummaryAttribute() {
        $str = '';
        if ($this->hasAttribute('description')) {
            if ($str = trim($this->attributes['description'])) {
                $str = str_replace('\n', "\n", $str);
                $str = preg_replace('/\s+/', ' ', $str);
                if (strlen($str) > 27) {
                    $str = substr($str, 0, 24) . '...';
                }
            }
        }

        return $str;
    }

    /**
     * @return bool|\DateInterval
     */
    public function getDurationAttribute() {
        if ($this->hasAttribute('start') && $this->hasAttribute('stop')) {
            if (! empty($this->attributes['start']) && ! empty($this->attributes['stop'])) {
                $start_parts = explode(':', $this->attributes['start']); // "08:00"
                $stop_parts = explode(':', $this->attributes['stop']);   // "16:38"
                $Start = Carbon::today()->hour(intval($start_parts[0]))->minute(intval(preg_replace('/[^0-9]/', '', $start_parts[1])));
                $Stop = Carbon::today()->hour(intval($stop_parts[0]))->minute(intval(preg_replace('/[^0-9]/', '', $stop_parts[1])));

                return $Start->diff($Stop);
            }
        }
    }

    /**
     * @return string
     */
    public function getDurationStringAttribute() {
        $output = '';
        if ($DateInterval = $this->getDurationAttribute()) {
            if ($DateInterval->h) {
                $output .= $DateInterval->h.($DateInterval->h > 1 ? ' hrs' : ' hr');
            }
            if ($DateInterval->i) {
                if (strlen($output)) {
                    $output .= ', ';
                }
                $output .= $DateInterval->i.($DateInterval->i > 1 ? ' mins' : ' min');
            }
        }

        return $output;
    }

    /**
     * @return string
     */
    public function getDateStringAttribute() {
        if ($this->hasAttribute('date')) {
            $date = $this->attributes['date'];
            if (! ($date instanceof Carbon)) {
                $date = new Carbon($date);
            }

            return $date->toDateString();
        }
        return '';
    }

    /**
     * @return string
     */
    public function getFriendlyDateStringAttribute() {
        if ($this->hasAttribute('date')) {
            $date = $this->attributes['date'];
            if (! ($date instanceof Carbon)) {
                $date = new Carbon($date);
            }

            return $date->toFormattedDateString();
        }
        return '';
    }

    /**
     * @return string eg. '14:15:16'
     */
    public function getStartTimeStringAttribute() {
        if ($this->hasAttribute('start') && $this->hasAttribute('date')) {
            return Carbon::parse(substr($this->attributes['date'], 0, 10).' '.$this->attributes['start'])->toTimeString();
        }
    }

    /**
     * @return string eg. '16:15:14'
     */
    public function getStopTimeStringAttribute() {
        if ($this->hasAttribute('stop') && $this->hasAttribute('date')) {
            return Carbon::parse(substr($this->attributes['date'], 0, 10).' '.$this->attributes['stop'])->toTimeString();
        }
    }

    /**
     * @return string eg. '2:15 pm'
     */
    public function getStartStringAttribute() {
        if ($this->hasAttribute('start')) {
            return Str::time($this->attributes['start']);
        }
    }

    /**
     * @return string eg. '4:15 pm'
     */
    public function getStopStringAttribute() {
        if ($this->hasAttribute('stop')) {
            return Str::time($this->attributes['stop']);
        }
    }



    /**
     * Setters
     */

    /**
     * @param $value
     */
    public function setDescriptionAttribute($value) {
        $this->attributes['description'] = trim($value);
    }

    /**
     * @param $field
     * @param null $default
     * @return string
     */
    public function promptForAttribute($field, $default = null) {
        $prompt = parent::promptForAttribute($field, $default);

        if (is_null($default)) {
            $default = $this->defaultValue($field);
        }

        // UPDATE default values
        if ($this->exists) {
            switch($field) {
                case 'start':
                    $default = $this->start_string;
                    break;
                case 'stop':
                    $default = $this->stop_string;
                    break;
                case 'date':
                    $default = $this->date_string;
                    break;
                case 'description':
                    $default = $this->description_summary;
                    break;
            }

            // INSERT default values
        } else {
            switch($field) {
                case 'start':
                case 'stop':
                    $default = Str::datetime(date('Y-m-d').' '.$default.':00', 'g:i a');
                    break;
            }
        }

        if ($config = static::field($field)) {
            if (array_key_exists('prompt', $config)) {
                if (is_null($default)) {
                    $default = $this->defaultValue($field);
                }
                $prompt = $config['prompt'];
                if ($config['required']) {
                    $prompt .= ' (required)';
                }
                if ($default) {
                    $prompt .= ' [' . $default . ']';
                }
                $prompt .= ': ';
            }
        }

        return $prompt;
    }

    /**
     * @param array $where
     * @return null
     */
    public function lastTask($where = []) {
        $Latest = null;
        $LastTask = null;

        if (empty($where)) {
            $where = [ ['date', 'LIKE', Carbon::now()->toDateString().' %'] ];
        }

        if ($Tasks = Task::where($where)->defaultSort()->get()) {
            foreach ($Tasks as $Task) {
                if ($Task->hasAttribute('stop')) {
                    $stop_parts = explode(':', $Task->stop);
                    if (! is_numeric($stop_parts[1])) {
                        if (false !== stripos($stop_parts[1], 'p') && intval($stop_parts[0]) < 12) {
                            $stop_parts[0] = intval($stop_parts[0]) + 12;
                        }
                        $stop_parts[1] = preg_replace('/[^0-9]/', '', $stop_parts[1]);
                    }
                    $Stop = Carbon::parse($Task->date)->hour($stop_parts[0])->minute($stop_parts[1]);

                    if (is_null($Latest) || $Latest->lt($Stop)) {
                        $Latest = $Stop->copy();
                        $LastTask = $Task;
                    }
                }
            }
        }

        return $LastTask;
    }

    /**
     * @param $field
     * @param null $default
     * @return mixed|null
     */
    public function defaultValue($field, $default = null) {
        $default = parent::defaultValue($field, $default);

        if (! $this->exists) {
            if ($field == 'start' && $LastTask = $this->lastTask()) {
                $default = $LastTask->stop;
            }
        }

        return $default;
    }
}