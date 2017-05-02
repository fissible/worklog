<?php
/**
 * UpdateEnvCommand
 * User: allenmccabe
 * Date: 4/20/17
 * Time: 3:15 PM
 */

namespace Worklog\CommandLine;

use Worklog\Filesystem\File;
use Worklog\Str;

class UpdateEnvCommand extends Command
{
    public $command_name;

    public static $description = 'Update local .env';
    public static $options = [
        'a' => ['req' => null, 'description' => 'Prompt for all values'],
        'q' => ['req' => null, 'description' => 'Check if .env is missing keys from .env.example'],
    ];
    public static $arguments = [ 'date', 'date_end' ];
    public static $usage = '%s [-ilt] [date [end_date]]';
    public static $menu = false;

    public function run()
    {
        parent::run();

        $env = $env_example = [ 'file' => null, 'data' => [] ];
        $base_path = dirname(APPLICATION_PATH);
        $env['file'] = new File($base_path.'/.env');
        $env_example['file'] = new File($base_path.'/.env.example');
        $update = false;
        $query = false;
        $all = false;
        $updated = 0;

        if ($this->option('a') || $this->getData('all')) {
            $all = true;
        }

        if ($this->option('q') || $this->getData('query')) {
            $query = true;
        }

        foreach ($env_example['file']->lines() as $line) {
            list($key, $val) = $this->parse_line($line);
            if ($key) {
                $env_example['data'][$key] = $val;
            }
        }

        if ($env['file']->exists()) {
            foreach ($env['file']->lines() as $line) {
                list($key, $val) = $this->parse_line($line);
                if ($key) {
                    $env['data'][$key] = $val;
                }
            }

            foreach ($env_example['data'] as $key => $eg_val) {
                if ($all || ! array_key_exists($key, $env['data'])) {
                    if ($query) {
                        $update = true;
                    } else {
                        $prompt = $key;
                        $default = (array_key_exists($key, $env['data']) ? $env['data'][$key] : $eg_val);
                        if (strlen($default) > 0) {
                            $prompt .= ' [' . $default . ']';
                        }
                        $prompt .= ': ';
                        $response = Str::parse(Input::ask($prompt, $default));

                        if (! array_key_exists($key, $env['data']) || $response !== $env['data'][$key]) {
                            if (! is_string($response)) {
                                switch (true) {
                                    case (true === $response):
                                        $response = 'true';
                                        break;
                                    case (false === $response):
                                        $response = 'false';
                                        break;
                                }
                            }

                            $env['data'][$key] = $response;
                            $update = true;
                        }
                    }
                }
            }

        } else {
            $env['data'] = $env_example['data'];
            $update = true;
        }

        if ($query) {
            if (IS_CLI && ! $this->internally_invoked()) {
                if ($update) {
                    return 'The Environment file is out of sync with the example.';
                } else {
                    return 'The Environment file is in sync with the example.';
                }
            } else {
                return $update;
            }
        } else {
            if ($update) {
                $updated = $this->write_file($env);
            }
        }

        if (IS_CLI && ! $this->internally_invoked()) {
            switch (true) {
                case (0 === $updated):
                    return 'No changes';
                    break;
                case (false === $updated):
                    return 'There was an error updating the Environment file';
                    break;
                default:
                    return 'Environment file updated';
                    break;
            }
        }

        return $updated;
    }

    /**
     * @param $line
     * @return array
     */
    private function parse_line($line)
    {
        $key = $val = null;

        if (! empty($line)) {
            $parts = explode('=', $line);

            if (! isset($parts[1])) {
                $parts[1] = null;
            }

            $key = trim($parts[0]);
            $val = trim($parts[1]);
        }

        return [ $key, $val ];
    }

    /**
     * @param $file
     */
    private function write_file($file)
    {
        $lines = [];
        foreach ($file['data'] as $key => $val) {
            $lines[] = $key.'='.$val;
        }

        return $file['file']->overwrite($lines, "\n");
    }
}
