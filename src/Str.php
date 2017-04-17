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
     * @param $input
     * @param $needles
     * @return bool
     */
    public static function contains($input, $needles) {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_strpos($input, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $haystack
     * @param $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles) {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && substr($haystack, 0, strlen($needle)) === (string)$needle) {
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
    public static function endsWith($input, $needles) {
        foreach ((array)$needles as $needle) {
            if (substr($input, -strlen($needle)) === (string)$needle) {
                return true;
            }
        }

        return false;
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
     * @return int
     */
    public static function length($input) {
        return mb_strlen($input);
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public static function lower($value) {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public static function upper($value) {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * @param $string
     * @param $start
     * @param null $length
     * @return string
     */
    public static function substr($string, $start, $length = null) {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * @param $value
     * @param int $limit
     * @param string $end
     * @return string
     */
    public static function limit($value, $limit = 100, $end = '...') {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * @param $value
     * @param int $words
     * @param string $end
     * @return string
     */
    public static function words($value, $words = 100, $end = '...') {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);
        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Parse a "Class@method" style callback string into class and method.
     *
     * @param  string $callback
     * @param  string|null $default
     * @return array
     */
    public static function parseCallback($callback, $default = null) {
        return static::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
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
            $method = substr($input, 0, $open_paren_pos);
            if ($arg_string = substr($input, $open_paren_pos + 1, (strpos($input, ')', $open_paren_pos) - $open_paren_pos) - 1)) {
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

        return $filename;
    }

    /**
     * @param $input
     * @return mixed
     */
    public static function camel($input) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }

    /**
     * @param $input
     * @param $delimiter
     * @return string
     */
    public static function snake($input, $delimiter = '_') {
        $out = [];
        $parts = str_split($input);
        foreach ($parts as $key => $char) {
            if ($char) {
                $last_out_key = count($out) - 1;
                $last_out_char = ($last_out_key >= 0 && isset($out[$last_out_key]) ? $out[$last_out_key] : null);
                if ($key > 0 && static::is_upper($char) && $last_out_char !== $delimiter) {
                    $out[] = $delimiter;
                }
                $out[] = $char;
            }
        }

        $output = trim(static::lower(implode('', $out)), $delimiter);

        return $output;
    }

    public static function studly($input) {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
    }

    /**
     * @param $value
     * @return mixed|string
     */
    public static function title($value) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param $input
     * @return mixed|string
     */
    public static function plural($input) {
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

        if (!in_array(static::lower($input), $already_plural)) {
            if (in_array(static::lower($input), array_keys($special_plural))) {
                $output = $special_plural[static::lower($input)];
            } else {
                switch (true) {
                    case (substr($input, -2) == 'th'):
                    case (substr($input, -2) == 'es'):
                    case (substr($input, -2) == 'us'):
                    case (substr($input, -3) == 'ies'):
                        break;
                    case (substr($input, -2) == 'is'):
                        $output = substr_replace($input, 'es', -2);
                        break;
                    case (substr($input, -2) == 'us'):
                        $output = substr_replace($input, 'i', -2);
                        break;
                    case (substr($input, -1) == 'y'):
                        if (static::is_consonant($input, -2, 1)) {
                            $output = substr_replace($input, 'ies', -1);
                        } else {
                            $output .= 's';
                        }
                        break;
                    case (substr($input, -1) == 'h'):
                    case (substr($input, -1) == 'o' && static::is_consonant($input, -2, 1)):
                    case (substr($input, -1) == 's'):
                        $output = $input . 'es';
                        break;
                    default:
                        $output .= 's';
                        break;
                }
            }
        }

        if (static::is_upper(substr($input, 0, 1))) {
            if (static::is_upper($input)) {
                $output = mb_strtoupper($output);
            } else {
                $output = ucfirst($output);
            }
        }


        return $output;
    }

    public static function is_vowel($char) {
        if (mb_strlen($char) > 1) {
            throw new \InvalidArgumentException('is_vowel() requires a single character string');
        }
        $char = strtolower($char);

        return in_array($char, ['a', 'e', 'i', 'o', 'u']);
    }

    public static function is_consonant($char) {
        if (mb_strlen($char) > 1) {
            throw new \InvalidArgumentException('is_consonant() requires a single character string');
        }

        return !static::is_vowel($char);
    }

    public static function is_lower($input) {
        return ctype_lower($input);
    }

    public static function is_upper($input) {
        return ctype_upper($input);
    }
}