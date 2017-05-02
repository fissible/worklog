<?php
namespace Worklog\Filesystem;

class File
{
    protected $path;

    private $is_dir = false;

    protected static $exception_strings = [
        'file_not_found' => 'No such file or directory'
    ];

    public function __construct($path, $directory = false)
    {
        $this->set_path($path);
        $this->set_directory_type($directory);
    }

    public function set_directory_type($is_dir = true)
    {
        $this->is_dir = $is_dir;
    }

    public function exists()
    {
        return file_exists($this->path);
    }

    public function is_directory()
    {
        $this->is_dir = is_dir($this->path);

        return $this->is_dir;
    }

    public function is_exec()
    {
        return is_executable($this->path);
    }

    public function directory($levels = 1)
    {
        return dirname($this->path, $levels);
    }

    /**
     * Return MD5 hash of the file
     * @throws \Exception
     * @return string
     */
    public function hash()
    {
        if ($this->exists()) {
            return md5_file($this->path);
        }
        throw new \Exception(sprintf('File::hash: "%s": '.static::$exception_strings['file_not_found'], $this->path));
    }

    public function set_path($path)
    {
        if (strlen($path) < 1) {
            throw new \InvalidArgumentException('Filename cannot be empty');
        }
        $this->path = str_replace(basename($path), static::sanitize(basename($path)), $path);
    }

    public function lines()
    {
        if ($this->exists()) {
            return file($this->path, FILE_IGNORE_NEW_LINES);
        }
        throw new \Exception(sprintf('File::lines: "%s": '.static::$exception_strings['file_not_found'], $this->path));
    }

    public function name()
    {
        return basename($this->path);
    }

    public function path()
    {
        return $this->path;
    }

    public function touch($perms = 0777)
    {
        if (! $this->exists()) {
            if ($this->is_dir) {
                printl($this->path);

                return mkdir($this->path, $perms, true);
            } else {
                return exec('touch "'.$this->path.'"');
            }
        }
    }

    public function contents()
    {
        return file_get_contents($this->path);
    }

    public function write($content = null, $flags = null, $line_glue = '')
    {
        if (is_null($flags)) {
            $flags = FILE_APPEND | LOCK_EX;
        }
        if (is_array($content)) {
            $content = implode($line_glue, $content);
        }

        return file_put_contents($this->path, $content, $flags);
    }

    public function overwrite($content, $line_glue = '')
    {
        return $this->write($content, LOCK_EX, $line_glue);
    }

    public function delete()
    {
        return unlink($this->path);
    }

    /**
     * Ensure a valid filename string
     * @param  string $filename
     * @return string
     */
    public static function sanitize($filename)
    {
        // Remove anything which isn't a word, whitespace, number
        // or any of the following caracters -_,;[]().
        $filename = mb_ereg_replace("([^\w\s\d\-_,;\[\]\(\).])", '', $filename);
        // Remove any runs of periods
        $filename = mb_ereg_replace("([\.]{2,})", '', $filename);

        return $filename;
    }

    public static function camel_case($input)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }

    public static function snake_case($input)
    {
        $out = [];
        $parts = str_split($input);
        foreach ($parts as $key => $char) {
            $ord = ord($char);
            $last_out_key = count($out) -1;
            $last_out_char = ($last_out_key >= 0 && isset($out[$last_out_key]) ? $out[$last_out_key] : null);
            if ($key > 0 && $ord > 64 && $ord < 91 && $last_out_char !== '_') $out[] = '_';
            $out[] = $char;
        }

        return strtolower(implode('', $out));
    }
}
