<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/10/17
 * Time: 10:04 AM
 */

namespace Worklog;


use Carbon\Carbon;

class Str
{
    const LOWERCASE = 0;
    const UPPERCASE = 1;

    /**
     * The underlying string value
     * @var string
     */
    private $string;


    public function __construct($string = '', $trim = false) {
        $this->setString($string, $trim);
    }

    private function setString($string = '', $trim = false) {
        $this->string = $string . '';
        if ($trim) {
            $this->trim();
        }
        return $this;
    }

    public static function date($input, $format = null) {
        if ($input instanceof Carbon) {
            $Date = $input->copy();
        } else {
            $Date = Carbon::parse($input);
        }
        if (is_null($format)) {
            return $Date->toDateString();
        }
        return $Date->format($format);
    }

    /**
     * @param string $time
     * @param string $format
     * @param null $timezone
     * @return mixed
     */
    public static function datetime($time = 'now', $format = 'Y-m-d H:i:s', $timezone = null) {
        if (strtolower($time) === 'now') $time = null;
        $Date = new Carbon($time, $timezone);
        return $Date->format($format);
    }

    public static function time($time_str, $format = 'g:i a') {
        $Date = Carbon::parse(date("Y-m-d").' '.$time_str);
        return $Date->format($format);
    }

    /**
     * @param $input
     * @param $needles
     * @return bool
     */
    public static function _contains($input, $needles) {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_strpos($input, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $char
     * @return bool
     */
    public static function _is_vowel($char) {
        if (mb_strlen($char) > 1) {
            throw new \InvalidArgumentException('_is_vowel() requires a single character string');
        }
        $char = strtolower($char);

        return in_array($char, ['a', 'e', 'i', 'o', 'u']);
    }

    /**
     * @param $char
     * @return bool
     */
    public static function _is_consonant($char) {
        if (mb_strlen($char) > 1) {
            throw new \InvalidArgumentException('_is_consonant() requires a single character string');
        }

        return !static::_is_vowel($char);
    }

    /**
     * @param $input
     * @return bool
     */
    public static function _is_lower($input) {
        return ctype_lower($input);
    }

    /**
     * @param $input
     * @return bool
     */
    public static function _is_upper($input) {
        return ctype_upper($input);
    }

    /**
     * @param $haystack
     * @param $needles
     * @return bool
     */
    public static function _startsWith($haystack, $needles) {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_substr($haystack, 0, strlen($needle)) === (string)$needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $input
     * @param $needles
     * @return bool
     */
    public static function _endsWith($input, $needles) {
        foreach ((array)$needles as $needle) {
            if (mb_substr($input, -strlen($needle)) === (string)$needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $input
     * @return int
     */
    public static function _length($input) {
        return mb_strlen($input);
    }

    /**
     * @param $value
     * @param int $limit
     * @param string $end
     * @return string
     */
    public static function _limit($value, $limit = 100, $end = '...') {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, ($limit - mb_strlen($end)), '', 'UTF-8')) . $end;
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public static function _lower($value) {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public static function _upper($value) {
        return mb_strtoupper($value, 'UTF-8');
    }

    public static function _replace($subject, $find, $replace, $regex = false, $count = null) {
        //mb_substr_replace
        if ($regex) {
            if (is_null($count)) {
                return preg_replace($find, $replace, $subject);
            } else {
                return preg_replace($find, $replace, $subject, $count);
            }
        } else {
            if (is_null($count)) {
                return str_replace($find, $replace, $subject);
            } else {
                return str_replace($find, $replace, $subject, $count);
            }
        }
    }

    /**
     * @param $value
     * @return string
     */
    public static function _shuffle($value) {
        return str_shuffle($value);
    }

    /**
     * @param $string
     * @param $start
     * @param null $length
     * @return string
     */
    public static function _substr($string, $start, $length = null) {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * @param $input
     * @param string $character_mask
     * @return string
     */
    public static function _trim($input, $character_mask = " \t\n\r\0\x0B") {
        return trim($input, $character_mask);
    }

    /**
     * @param $input
     * @param string $character_mask
     * @return string
     */
    public static function _ltrim($input, $character_mask = " \t\n\r\0\x0B") {
        return ltrim($input, $character_mask);
    }

    /**
     * @param $input
     * @param string $character_mask
     * @return string
     */
    public static function _rtrim($input, $character_mask = " \t\n\r\0\x0B") {
        return rtrim($input, $character_mask);
    }

    /**
     * @param $value
     * @param int $words
     * @param string $end
     * @return string
     */
    public static function _words($value, $words = 100, $end = '...') {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);
        if (!isset($matches[0]) || static::_length($value) === static::_length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    public static function parse($input, $default = null) {
        $output = $input;

        if (is_null($input) || strtolower($input) === 'null') {
            $output = $default;
        } elseif (in_array(strtolower($input), ['false', 'true', 'yes', 'no', 'on', 'off'])) {
            $output = filter_var($input, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_numeric($input)) {
            if ((int) $input == $input) {
                $output = intval($input);
            } else {
                $output = floatval($input);
            }
        }

        return $output;
    }

    /**
     * Parse a "Class@method" style callback string into class and method.
     *
     * @param  string $callback
     * @param  string|null $default
     * @return array
     */
    public static function parseCallback($callback, $default = null) {
        return static::_contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
    }

    /**
     * Parse a "Class::method('format')" string callback into method and arguments.
     *
     * @param $input
     * @return array
     */
    public static function parseFunctionArgs($input) {
        $output = $input;

        if (false !== strpos($input, '(') && false !== strpos($input, ')')) {
            $open_paren_pos = strpos($input, '(');
            $method = mb_substr($input, 0, $open_paren_pos);
            if ($arg_string = mb_substr($input, $open_paren_pos + 1, (strpos($input, ')', $open_paren_pos) - $open_paren_pos) - 1)) {
                $arguments = explode(',', $arg_string);
                $arguments = array_map('trim', $arguments);
                $arguments = array_map(function ($n) {
                    return trim($n, "'");
                }, $arguments);

                $output = [ $method, $arguments ];
            }
        }

        return $output;
    }

    public static function random($seed, $length = 0) {
        $string = '';
        if (is_string($seed) && ! is_numeric($string)) {
            $string = (new static($seed))->randomize($length);
        } elseif (is_numeric($seed) && $seed > 0) {
            $string = (new static())->randomize($seed);
        }

        return $string;
    }

    /**
     * @mutator
     * @param int $length
     * @return $this
     */
    public function randomize($length = 0) {
        if ($length < 1) {
            if (strlen($this->string) > 0) {
                $length = strlen($this->string);
            } else {
                throw new \RuntimeException('Cannot randomize an empty string');
            }
        }

        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= chr(mt_rand(33, 126));
        }

        return new Str($string);
    }

    /**
     * Ensure a valid filename string
     * @param  string $filename
     * @return string
     */
    public static function sanitize($filename) {
        // Remove anything which isn't a word, whitespace, number
        // or any of the following caracters -_,;[]().
        $filename = mb_ereg_replace("([^\w\s\d\-_,;\[\]\(\).])", '', $filename);
        // Remove any runs of periods
        $filename = mb_ereg_replace("([\.]{2,})", '', $filename);

        return new Str($filename);
    }

    /**
     * @param $input
     * @return mixed
     */
    public static function _camel($input) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }

    /**
     * @param $input
     * @param $delimiter
     * @return string
     */
    public static function _snake($input, $delimiter = '_') {
        $out = [];
        $parts = str_split($input);
        foreach ($parts as $key => $char) {
            if ($char) {
                $last_out_key = count($out) - 1;
                $last_out_char = ($last_out_key >= 0 && isset($out[$last_out_key]) ? $out[$last_out_key] : null);
                if ($key > 0 && static::_is_upper($char) && $last_out_char !== $delimiter) {
                    $out[] = $delimiter;
                }
                $out[] = $char;
            }
        }

        $output = trim(static::_lower(implode('', $out)), $delimiter);

        return $output;
    }

    public static function _studly($input) {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public static function _title($value) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param $input
     * @return mixed|string
     */
    public static function _plural($input) {
        $output = $input = trim($input);
        $already_plural = [
            'aircraft', 'bison', 'buffalo', 'chinese', 'data', 'deer', 'duck',
            'education', 'equipment', 'evidence', 'feedback', 'fish', 'furniture', 'gold', 'information',
            'metadata', 'money', 'rain', 'rice', 'salmon', 'series', 'sheep', 'species', 'swine', 'swiss', 'traffic', 'trout', 'wheat'
        ];
        $special_plural = [
            'bath' => 'baths',
            'calf' => 'calves',
            'child' => 'children',
            'foot' => 'feet',
            'fungus' => 'fungi',
            'leaf' => 'leaves',
            'man' => 'men',
            'medium' => 'media',
            'mouse' => 'mice',
            'peron' => 'people',
            'photo' => 'photos',
            'piano' => 'pianos',
            'pro' => 'pros',
            'radius' => 'radii',
            'status' => 'statuses',
            'tooth' => 'teeth',
            'woman' => 'women',
            'zero' => 'zeros'
        ];

        if (!in_array(static::_lower($input), $already_plural)) {
            if (in_array(static::_lower($input), array_keys($special_plural))) {
                $output = $special_plural[static::_lower($input)];
            } else {
                switch (true) {
                    case (mb_substr($input, -2) == 'th'):
                    case (mb_substr($input, -2) == 'es'):
                    case (mb_substr($input, -2) == 'us'):
                    case (mb_substr($input, -3) == 'ies'):
                        break;
                    case (mb_substr($input, -2) == 'is'):
                        $output = mb_substr_replace($input, 'es', -2);
                        break;
                    case (mb_substr($input, -2) == 'us'):
                        $output = mb_substr_replace($input, 'i', -2);
                        break;
                    case (mb_substr($input, -1) == 'y'):
                        if (static::_is_consonant($input, -2, 1)) {
                            $output = mb_substr_replace($input, 'ies', -1);
                        } else {
                            $output .= 's';
                        }
                        break;
                    case (mb_substr($input, -1) == 'h'):
                    case (mb_substr($input, -1) == 'o' && static::_is_consonant($input, -2, 1)):
                    case (mb_substr($input, -1) == 's'):
                        $output = $input . 'es';
                        break;
                    default:
                        $output .= 's';
                        break;
                }
            }
        }

        if (static::_is_upper(mb_substr($input, 0, 1))) {
            if (static::_is_upper($input)) {
                $output = mb_strtoupper($output);
            } else {
                $output = ucfirst($output);
            }
        }


        return $output;
    }

    /**
     * Return the underlying string
     * @return string
     */
    public function base()
    {
        return $this->string;
    }

    public function __toString()
    {
        return $this->string;
    }

    public function __call($name, $arguments) {
        array_unshift($arguments, $this->base());
        switch ($name) {
            case 'startsWith':
            case 'contains':
            case 'endsWith':
            case 'is_vowel':
            case 'is_consonant':
            case 'is_upper':
            case 'is_lower':
            case 'length':
                return call_user_func_array([static::class, '_'.$name], $arguments);
                break;
            default:
            case 'camel':
            case 'limit':
            case 'lower':
            case 'plural':
            case 'replace':
            case 'shuffle':
            case 'studly':
            case 'snake':
            case 'substr':
            case 'title':
            case 'upper':
            case 'words':
                return new static(call_user_func_array([static::class, '_'.$name], $arguments));
                break;
        }
    }

    public static function __callStatic($name, $arguments) {
        return call_user_func_array([static::class, '_'.$name], $arguments);
    }
}