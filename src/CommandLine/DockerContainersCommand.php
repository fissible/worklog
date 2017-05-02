<?php
namespace Worklog\CommandLine;

use Worklog\Filesystem\File;

/**
 * DockerContainersCommand
 * List all containers
 */
class DockerContainersCommand extends Command
{
    public $command_name;

    public static $description = 'List Docker containers';
    public static $options = [
        'a' => ['req' => null, 'description' => 'Show all containers (default shows just running)'],
        'f' => ['req' => false, 'description' => 'Filter output based on conditions provided (default [])'],
        'format' => ['req' => true, 'description' => 'Pretty-print containers using a Go template']
        // 'k' => ['req' => null, 'description' => 'Return commands that take arguments']
    ];
    private $required_data = [ /*'file'*/ ];
    private $docker_binary = '/usr/local/bin/docker';

    /*
    List containers

    Options:
      -a, --all             Show all containers (default shows just running)
      -f, --filter value    Filter output based on conditions provided (default [])
                            - exited=<int> an exit code of <int>
                            - label=<key> or label=<key>=<value>
                            - status=(created|restarting|running|paused|exited)
                            - name=<string> a container's name
                            - id=<ID> a container's ID
                            - before=(<container-name>|<container-id>)
                            - since=(<container-name>|<container-id>)
                            - ancestor=(<image-name>[:tag]|<image-id>|<image@digest>)
                              containers created from an image or a descendant.
          --format string   Pretty-print containers using a Go template
          --help            Print usage
      -n, --last int        Show n last created containers (includes all states) (default -1)
      -l, --latest          Show the latest created container (includes all states)
          --no-trunc        Don't truncate output
      -q, --quiet           Only display numeric IDs
      -s, --size            Display total file sizes
     */

    public function run()
    {
        parent::run();

        $columns = $rows = $lengths = [];
        $command = 'ps';
        $options = '';

        if (! $this->option('format')) {
            $this->Options()->Option('format', 'table {{.ID}}\t{{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Mounts}}\t{{.Size}}\t{{.RunningFor}}\t{{.Status}}');
        }

        foreach ($this->Options()->all() as $option => $val) {
            if (strlen($options)) $options .= ' ';
            $Option = $this->Options()->Option($option);
            $options .= $Option->as_cli_string();
        }
        exec($this->docker_binary.' '.$command.($options ? ' '.$options : ''), $output);

        // parse output
        foreach ($output as $lkey => $line) {
            $line_len = strlen($line);
            if (empty($columns)) {
                $columns = preg_split('/( ){2,}/', $line);

                foreach ($columns as $__key => $value) {
                    $start_pos = strpos($line, $value);
                    $stop_pos = $start_pos + (strlen($value) - 1);
                    $columns[$__key] = [ 'value' => trim($value), 'start' => $start_pos, 'stop' => $stop_pos ];
                    // $lengths[$__key] = ($stop_pos + 1) - $start_pos;
                    $lengths[$__key] = strlen(trim($value));
                }
            } else {
                $data = [];
                foreach ($columns as $key => $col) {
                    // 0 => [ 'value' => 'CONTAINER ID', 'start' => 0, 'stop' => 11 ]
                    $next_key = $key + 1;
                    $next = (isset($columns[$next_key]) ? $columns[$next_key] : null);
                    $data[$key] = trim(substr($line, $col['start'], ($next ? $next['start'] - $col['start'] : $line_len - $col['start'])));

                    if ($col['value'] == 'PORTS') {
                        $data[$key] = str_replace('0.0.0.0:', '', $data[$key]);
                        // "82->80/tcp                                                         "
                        // "5044->5044/tcp, 5601->5601/tcp, 9200->9200/tcp, 9300/tcp           "
                        if (strstr($data[$key], '->')) {
                            $data_parts = strstr($data[$key], ',') ? explode(', ', $data[$key]) : [ $data[$key] ];
                            foreach ($data_parts as $_key => $value) {
                                if (strstr($value, '->')) {
                                    if (strstr($value, '/')) {
                                        $value = substr($value, 0, strpos($value, '/'));
                                    }
                                    $ports = explode('->', $value);
                                    if ($ports[0] === $ports[1]) {
                                        $data_parts[$_key] = str_replace($ports[0].'->'.$ports[0], $ports[0], $data_parts[$_key]);
                                    }
                                }
                            }
                            $data[$key] = implode(', ', $data_parts);
                        }
                    }

                    $field_length = strlen($data[$key]);//($next ? $next['start'] - $col['start'] : $line_len - $col['start']);
                    if ($field_length > $lengths[$key]) {
                        $lengths[$key] = $field_length;
                    }
                }
                $rows[] = $data;
            }
        }

        // printl($columns);
        // printl($rows);
        // printl($lengths);
        return Output::data_grid(array_column($columns, 'value'), $rows, $lengths);
    }
}
