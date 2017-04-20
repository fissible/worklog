<?php
namespace Worklog\CommandLine;

class Output {

    protected static $allow_unicode;

	protected static $line_length;

    protected static $variant;

    protected static $foreground_colors = [
        'black' => '0;30',
		'dark_gray' => '1;30',
		'blue' => '0;34',
		'light_blue' => '1;34',
		'green' => '0;32',
		'light_green' => '1;32',
		'cyan' => '0;36',
		'light_cyan' => '1;36',
		'red' => '0;31',
		'light_red' => '1;31',
		'purple' => '0;35',
		'light_purple' => '1;35',
		'brown' => '0;33',
		'yellow' => '1;33',
		'light_gray' => '0;37',
		'white' => '1;37'
    ];

    protected static $background_colors = [
        'black' => '40',
		'red' => '41',
		'green' => '42',
		'yellow' => '43',
		'blue' => '44',
		'magenta' => '45',
		'cyan' => '46',
		'light_gray' => '47'
    ];

    protected static $control_chars = [
        'bold' => [ "\033[1m",  "\033[0m" ]
    ];

    protected static $unicode_borders = [
        'light' => [
            'hor' => '─',
            'ver' => '│',
            'down_right' => '┌',
            'down_left' => '┐',
            'up_right' => '└',
            'up_left' => '┘',
            'ver_right' => '├',
            'ver_left' => '┤',
            'down_hor' => '┬',
            'up_hor' => '┴',
            'cross' => '┼'
        ],
        'heavy' => [
            'hor' => '━',
            'ver' => '┃',
            'down_right' => '┏',
            'down_left' => '┓',
            'up_right' => '┗',
            'up_left' => '┛',
            'ver_right' => '┣',
            'ver_left' => '┫',
            'down_hor' => '┳',
            'up_hor' => '┻',
            'cross' => '╋'
        ],
        'double' => [
            'hor' => '═',
            'ver' => '║',
            'down_right' => '╔',
            'down_left' => '╗',
            'up_right' => '╚',
            'up_left' => '╝',
            'ver_right' => '╠',
            'ver_left' => '╣',
            'down_hor' => '╦',
            'up_hor' => '╩',
            'cross' => '╬'
        ]
    ];

    private static $temp_config = [];


    public static function init($allow_unicode = null, $line_length = null, $variant = null) {
        if (is_null($allow_unicode) && ! isset(static::$allow_unicode)) {
            $allow_unicode = false;
        }

        if (is_null($line_length) && ! isset(static::$line_length)) {
            $line_length = static::cols();
        }

        if (is_null($variant) && ! isset(static::$variant)) {
            $variant = 'light';
        }

        if (! is_null($allow_unicode)) {
            static::set_allow_unicode($allow_unicode);
        }

        if (! is_null($line_length)) {
            static::set_line_length($line_length);
        }

        if (! is_null($variant)) {
            static::set_variant($variant);
        }
    }

    public static function cols() {
        return exec('tput cols');
    }

    public static function rows() {
        return exec('tput lines');
    }

    public static function color($input, $color, $background_color = false) {
        $out = ""; $_out = '';

        if (is_object($input)) {
            $input = json_decode(json_encode($input), true);
        }
        if (is_array($input)) {
            foreach ($input as $str) {
                if (! empty($str)) {
                    $out .= static::color($str, $color, $background_color)."\n";
                }
            }
            $out = trim($out, "\n");
        } else {
            if (array_key_exists($color, static::$foreground_colors)) {
                $out .= "\033[" . static::$foreground_colors[$color] . "m";
            }

            if ($background_color) {
                if (array_key_exists($background_color, static::$background_colors)) {
                    $out .= "\033[" . static::$background_colors[$background_color] . "m";
                }
            }

            if (is_string($input)) {
                $out .= $input . "\033[0m";
            }
        }

        return $out;
    }

    protected static function control_chars() {
        $chars = static::$control_chars;
        foreach (static::$foreground_colors as $name => $code) {
            $chars['fg'.$name] = [ "\033[".$code."m", "\033[0m" ];
        }
        foreach (static::$background_colors as $name => $code) {
            $chars['bg'.$name] = [ "\033[".$code."m", "\033[0m" ];
        }

        return $chars;
    }

    public static function string_length($string) {
        $control_chars = static::control_chars();

        foreach ($control_chars as $name => $chars) {
            if (false !== mb_strpos($string, $chars[0])) {
                $string = str_replace($chars[0], '', $string);
            }
        }
        if (false !== mb_strpos($string, "\033[0m")) {
            $string = str_replace("\033[0m", '', $string);
        }

        if (function_exists('mb_strlen') && function_exists('mb_detect_encoding')) {
            $length = mb_strlen($string, mb_detect_encoding($string));
        } else {
            $string = iconv('ASCII' , 'ASCII', $string);
            $length = strlen($string);
        }

        return $length;
    }

    public static function uchar($char, $variant = null, $override_allow_unicode = false) {
        $out = $char;

        if (is_null($variant)) {
            $variant = static::variant();
        }

        if (substr($char, 0, 2) == '\u') {
            $out = json_decode('"'.$char.'"');
        } else {
            $aliases = [
                'top_l' => 'down_right',
                'top_r' =>'down_left',
                'hor' => 'hor',
                'ver' => 'ver',
                'mid_l' => 'ver_right',
                'mid_r' => 'ver_left',
                'bot_l' => 'up_right',
                'bot_r' => 'up_left',
                'top_ver' => 'down_hor',
                'bot_ver' =>'up_hor'
            ];
            if (array_key_exists($char, $aliases)) $char = $aliases[$char];
            if (array_key_exists($variant, static::$unicode_borders)) {
                if (array_key_exists($char, static::$unicode_borders[$variant])) {
                    $out = static::$unicode_borders[$variant][$char];
                }
            }
        }

        if (! static::$allow_unicode && false === $override_allow_unicode) {
            $out = static::non_unicode_variant($out, $variant);
        }

        return $out;
    }

    public static function non_unicode_variant($input, $variant = null) {
        $out = $input;

        if (is_null($variant)) {
            $variant = static::variant();
        }
        switch ($input) {
            case (static::line_joint('top:left',  $variant)):
            case (static::line_joint('top:right', $variant)):
            case (static::line_joint('mid:left',  $variant)):
            case (static::line_joint('mid:right', $variant)):
            case (static::line_joint('bot:left',  $variant)):
            case (static::line_joint('bot:right', $variant)):
                $out = '+';
                break;
            case (static::uchar('ver', $variant, true)):
                $out = '|';
                break;
            case (static::uchar('hor', $variant, true)):
                if ($variant == 'light') {
                    $out = '-';
                } else {
                    $out = '=';
                }
                break;
        }

        return $out;
    }

	public static function line($string = '', $border = '', $length = null, $pad_char = ' ', $variant = null) {
	    if (is_null($length)) {
            $length = static::line_length();
        }

        if (is_null($variant)) {
            $variant = static::variant();
        }

        $control_chars = static::control_chars();

        if ($border) {
		    if (is_string($border) && false !== mb_strpos($border, ',')) {
                $border = explode(',', $border);
            }
		    if (is_array($border)) {
                $variant = $border[1];
                $border = $border[0];
            } else {
                $border_left = $border_right = $border;
            }

            if (static::string_length($border) > 1) {
                $border = static::line_joint($border, $variant);
            }

            $length -= (static::string_length($border) + static::string_length($pad_char)) * 2;

            if (static::$allow_unicode) {
                switch ($border) {
                    case (static::line_joint('top:left',  $variant)):
                    case (static::line_joint('top:right', $variant)):
                        $border_left  = static::line_joint('top:left',  $variant);
                        $border_right = static::line_joint('top:right', $variant);
                        break;
                    case (static::line_joint('mid:left',  $variant)):
                    case (static::line_joint('mid:right', $variant)):
                        $border_left  = static::line_joint('mid:left',  $variant);
                        $border_right = static::line_joint('mid:right', $variant);
                        break;
                    case (static::line_joint('bot:left',  $variant)):
                    case (static::line_joint('bot:right', $variant)):
                        $border_left  = static::line_joint('mid:left',  $variant);
                        $border_right = static::line_joint('mid:right', $variant);
                        break;
                }
            }
		}
		
		if (static::string_length($string) > $length) {
			$string = wordwrap($string, $length, "\n", true);
			$string_parts = explode("\n", $string);
			$string_parts = array_reverse($string_parts);
			
			foreach ($string_parts as $_key => $_str) {
				if (trim($_str) == '') {
					unset($string_parts[$_key]);
				} else {
					break;
				}
			}
			$string_parts = array_reverse($string_parts);
			$string = implode("\n", $string_parts);
		}
/*
$line_length_default = static::line_length();
$variant_default = static::variant();
$allow_unicode_default = static::allow_unicode();
static::set_allow_unicode(true);
static::set_line_length($size * 2);
static::set_variant($variant);
$isize = ($size - 2);
$border = (static::allow_unicode() ? static::uchar('ver') : '|');

ob_start();

printl(static::horizontal_line('top'));
for ($i = 0; $i <= $isize; $i++) {
    if (strlen($title) > 0) {
        if ($i == 0) {
            static::line($title, $border);
        } elseif ($i == 1) {
            printl(static::horizontal_line('mid'));
        } else {
            static::line(' ', $border);
        }
    } else {
        static::line(' ', $border);
    }
}
printl(static::horizontal_line('bot'));

$out = ob_get_clean();

static::set_allow_unicode($allow_unicode_default);
static::set_line_length($line_length_default);
static::set_variant($variant_default);
 */
		if ($border) {
			if (false !== strpos($string, "\n") && $string !== "\n") {
				$str_parts = explode("\n", $string);
				$count = count($str_parts);
				foreach ($str_parts as $key => $str) {
					if ($key - 1 == $count && empty($str)) {
						continue;
					}
					$extra_length = 0;
					foreach ($control_chars as $cc_name => $cc_delims) {
						if (false !== mb_strpos($str, $cc_delims[0])) {
							$extra_length += mb_strlen($cc_delims[0]);
						}
					}
                    if (false !== mb_strpos($str, "\033[0m")) {
                        $extra_length += mb_strlen("\033[0m");
                    }
//					$strlen = mb_strlen($str) - $extra_length;
                    $strlen = static::string_length($str);

					if ($strlen < $length) {
						$str = mb_str_pad($str, $length + $extra_length, $pad_char);
					}
					$str_parts[$key] = $border_left.$pad_char.$str.$pad_char.$border_right;
				}
				$string = implode("\n", $str_parts);
			} else {
				$extra_length = 0;
				foreach ($control_chars as $cc_name => $cc_delims) {
					if (false !== mb_strpos($string, $cc_delims[0])) {
						$extra_length += mb_strlen($cc_delims[0]);
					}
				}
                if (false !== mb_strpos($string, "\033[0m")) {
                    $extra_length += mb_strlen("\033[0m");
                }
//				$strlen = mb_strlen($string) - $extra_length;
                $strlen = static::string_length($string);

				if ($strlen < $length) {
					$string = mb_str_pad($string, $length + $extra_length, $pad_char);
				}
				$string = $border_left.$pad_char.$string.$pad_char.$border_right;
			}
		}

		print $string."\n";
	}

    public static function set_allow_unicode($allow = true) {
        static::$allow_unicode = $allow;
    }

	public static function set_line_length($length) {
        static::$line_length = $length;
    }

    public static function set_variant($variant = 'light') {
        static::$variant = $variant;
    }

    public static function allow_unicode() {
        return static::$allow_unicode;
    }

    public static function line_length() {
    	return static::$line_length;
    }

    public static function variant() {
        return static::$variant;
    }

    public static function line_joint($flags, $variant = null) {
        $out = '+';
        if (is_null($variant)) {
            $variant = static::variant();
        }
        if (static::$allow_unicode) {
            if (! is_array($flags)) {
                $flags = explode(',', $flags);
            }
            foreach ($flags as $flag) {
                $flag = str_replace('-', '_', strtolower($flag));
                switch ($flag) {
                    case 'top:left':
                        $out = static::uchar('down_right', $variant);
                        break;
                    case 'top:right':
                        $out = static::uchar('down_left', $variant);
                        break;
                    case 'mid:left':
                    case 'middle:left':
                        $out = static::uchar('ver_right', $variant);
                        break;
                    case 'mid:right':
                    case 'middle:right':
                        $out = static::uchar('ver_left', $variant);
                        break;
                    case 'bot:left':
                    case 'bottom:left':
                        $out = static::uchar('up_right', $variant);
                        break;
                    case 'bot:right':
                    case 'bottom:right':
                        $out = static::uchar('up_left', $variant);
                        break;
                }
            }
        }

        return $out;
    }

    public static function horizontal_line($flags, $length = null, $variant = null) {
        if (is_null($length)) {
            $length = static::line_length();
        }
        if (is_null($variant)) {
            $variant = static::variant();
        }
        $left = (static::$allow_unicode ? static::uchar('ver_right', $variant) : '+');
        $horizontal = (static::$allow_unicode ? static::uchar('hor', $variant) : '-');
        $right = (static::$allow_unicode ? static::uchar('ver_left', $variant) : '+');



        if (! is_array($flags)) {
            $flags = explode(',', $flags);
        }
        foreach ($flags as $flag) {
            $flag = str_replace('-', '_', strtolower($flag));
            switch ($flag) {
                case 'top':
                    $left = static::line_joint($flag.':left', $variant);//(static::$allow_unicode ? static::uchar('down_right', $variant) : '+');
                    $right = static::line_joint($flag.':right', $variant);//(static::$allow_unicode ? static::uchar('down_left', $variant) : '+');
                    break;
                case 'mid':
                case 'middle':
                    $left = static::line_joint($flag.':left', $variant);//(static::$allow_unicode ? static::uchar('ver_right', $variant) : '+');
                    $right = static::line_joint($flag.':right', $variant);//(static::$allow_unicode ? static::uchar('ver_left', $variant) : '+');
                    break;
                case 'bot':
                case 'bottom':
                    $left = static::line_joint($flag.':left', $variant);//(static::$allow_unicode ? static::uchar('up_right', $variant) : '+');
                    $right = static::line_joint($flag.':right', $variant);//(static::$allow_unicode ? static::uchar('up_left', $variant) : '+');
                    break;
            }
        }

        return $left . str_repeat($horizontal, $length - (mb_strlen($left) + mb_strlen($right))) . $right;
    }

    /**
     * Format an array of data into an ASCII grid
     * @param  array $headers An array of column header strings
     * @param  array $rows Am array of arrays of strings
     * @param  string $template The printf string template, eg. "| %-10.10s | %-14.14s | %-55.55s | %-19.19s |"
     * @param null $max_width
     * @return string
     */
	public static function data_grid($headers, $rows, $template = null, $max_width = null) {
		$grid = '';
		$has_unspecified_width = false;
		$row_strings = $template_cols = $unspecified_width_keys = [];
		$header_count = count($headers);
		$row_count = count($rows);
		$max_line_length = ($max_width ?: static::$line_length);
		$max_col_width = floor($max_line_length / ($header_count ?: 1));
		$lengths = (is_array($template) ? $template : []);

//        static::set_config('allow_unicode', true);
//        static::set_config('line_length', $size * 2);
//        static::set_config('variant', $variant);
		
		if (empty($template) || is_array($template)) {
			if (empty($lengths)) {
				foreach ($headers as $hkey => $header) {
					$length = mb_strlen($header);
					foreach ($rows as $rkey => $rvalue) {
						if (isset($rvalue[$hkey])) {
							$value_length = mb_strlen($rvalue[$hkey]);
							if ($value_length > $length) {
								$length = $value_length;
							}
						}
					}

					if ($length > $max_col_width) {
						$length = $max_col_width;
					}
					$lengths[$hkey] = $length;
				}
			}
		}

		foreach ($lengths as $lkey => $length) {
			if (is_null($length)) {
				$has_unspecified_width = true;
				break;
			} elseif (isset($headers[$lkey]) && mb_strlen($headers[$lkey]) > $length) {
				$lengths[$lkey] = mb_strlen($headers[$lkey]);
			}
		}

		if (count($lengths) > 0 && ($has_unspecified_width || count($lengths) < count($headers))) {
			$total_length = 0;
			
			foreach ($headers as $hkey => $header) {
				if (! isset($lengths[$hkey]) || is_null($lengths[$hkey])) {
					// $lengths[$hkey] = mb_strlen($header);
					$unspecified_width_keys[] = $hkey;
				} else {
					$total_length += $lengths[$hkey];
				}
			}
			if ($total_length < $max_line_length && count($unspecified_width_keys)) {
				$remaining_width = $max_line_length - $total_length;
				$max_col_width = floor($remaining_width / count($unspecified_width_keys));
				
				foreach ($unspecified_width_keys as $ukey => $key) {
					$length = mb_strlen($headers[$key]);
					foreach ($rows as $rkey => $rvalue) {
						if (isset($rvalue[$key])) {
							$value_length = mb_strlen($rvalue[$key]);
							if ($value_length > $length) {
								$length = $value_length;
							}
						}
					}

//					if ($length > $max_col_width) { // what's this do?
//						$length = $max_col_width;
//					}

					$lengths[$key] = $max_col_width;
				}
			}
		}

		foreach ($lengths as $lkey => $length) {
			$template_cols[] = sprintf('%%-%ds', $length);
		}

//		if (static::$allow_unicode) {
//            $template = '| '.implode(' | ', $template_cols).' |';
//        } else {
//            $template = Output::uchar('ver').' '.implode(' '.Output::uchar('ver').' ', $template_cols).' '.Output::uchar('ver');
//        }
        $template = '| '.implode(' | ', $template_cols).' |';


		if ($row_count > 0) {
			foreach ($rows as $key => $row) {
				if (! empty($lengths)) {
					foreach ($row as $rkey => $cell) {
						if (isset($lengths[$rkey]) && is_numeric($lengths[$rkey]) && $lengths[$rkey] > 0) {
							$row[$rkey] = static::str_shorten($cell, $lengths[$rkey]);
						}
					}
				}
				
				$row_strings[] = vsprintf($template, $row);
			}
			$header_row = vsprintf($template, $headers);
			$hline = '+'.str_repeat('-', mb_strlen($header_row) - 2).'+';
			array_unshift($row_strings, $hline);
			array_unshift($row_strings, $header_row);
			array_unshift($row_strings, $hline);
			$row_strings[] = $hline;
			$grid = implode("\n", $row_strings);
		}

//        static::reset_config();

		return $grid;
	}

    /**
     * @param $size
     * @param string $title
     * @param string $variant
     * @return string
     */
    public static function box($size, $title = '', $variant = 'light') {
        static::set_config('allow_unicode', true);
        static::set_config('line_length', $size * 2);
        static::set_config('variant', $variant);

        $isize = ($size - 2);
        $border = (static::allow_unicode() ? static::uchar('ver') : '|');

        ob_start();

        printl(static::horizontal_line('top'));
        for ($i = 0; $i <= $isize; $i++) {
            if (strlen($title) > 0) {
                if ($i == 0) {
                    static::line($title, $border);
                } elseif ($i == 1) {
                    printl(static::horizontal_line('mid'));
                } else {
                    static::line(' ', $border);
                }
            } else {
                static::line(' ', $border);
            }
        }
        printl(static::horizontal_line('bot'));

        $out = ob_get_clean();

        static::reset_config();

        return $out;
    }

    /**
     * @param $input
     * @param $length
     * @param string $suffix
     * @return string
     */
	public static function str_shorten($input, $length, $suffix = '...') {
		$output = $input;
		if (mb_strlen($output) > $length) {
			$output = substr($output, 0, ($length - mb_strlen($suffix))).$suffix;
		}
		return $output;
	}

    /**
     * @param $setting
     * @param $value
     */
	public static function set_config($setting, $value) {
        $old_value = null;

        switch($setting) {
            case 'unicode':
            case 'allow_unicode':
                $setting = 'allow_unicode';
                $old_value = static::allow_unicode();
                static::set_allow_unicode($value);
                break;
            case 'length':
            case 'line_length':
                $setting = 'line_length';
                $old_value = static::line_length();
                static::set_line_length($value);
                break;
            case 'variant':
                $setting = 'variant';
                $old_value = static::variant();
                static::set_variant($value);
                break;
        }

	    static::$temp_config[$setting] = $old_value;
    }

    /**
     * Reset static config values to prior values
     */
    public static function reset_config() {
        if (! empty(static::$temp_config)) {
            foreach (static::$temp_config as $setting => $value) {
                switch($setting) {
                    case 'allow_unicode':
                        static::set_allow_unicode($value);
                        break;
                    case 'line_length':
                        static::set_line_length($value);
                        break;
                    case 'variant':
                        static::set_variant($value);
                        break;
                }
            }
        }
    }
}