<?php
namespace Worklog\CommandLine;

/**
 * ViewCacheCommand
 * Lists options this script takes (CLI)
 */
class ViewCacheCommand extends Command
{
    public $command_name;

    public static $description = 'Display local cache contents';
    public static $options = [];
    public static $arguments = [ 'tags' ];
    // public static $usage = '[] %s';
    public static $menu = false;

    public function run()
    {
        $output = "No cached data.";
        $tags = (array) $this->getData('tags');
        $grid_data = $raw_cache_data = [];
        $registry = $this->App()->Cache()->registry();
        // $template = [ 40, 38, 40, 19, 21 ];
        $template = [ 40, 38, null, null, null ];

        if (count($registry)) {
            foreach ($registry as $cache_name => $path) {
                $raw_cache_data = $this->App()->Cache()->load($cache_name, true);

                if ($tags && ! array_intersect($tags, $raw_cache_data['tags'])) {
                    continue;
                }

                $data_out = '-';
                if (is_scalar($raw_cache_data['data'])) {
                    $data_out = $raw_cache_data['data'];
                } elseif (is_array($raw_cache_data['data'])) {
                    $data_out = print_r($raw_cache_data['data'], 1);
                } else {
                    $data_out = get_class($raw_cache_data['data']);
                }
                $data_out = preg_replace('/[\r\n\s]+/', ' ', $data_out);

                $grid_data[] = [
                    $raw_cache_data['name'],
                    basename($path),
                    $data_out,
                    date("Y-m-d H:i:s", $raw_cache_data['expiry']),
                    implode(', ', $raw_cache_data['tags'])
                ];
            }

            if (count($grid_data)) {
                $output = Output::data_grid([ 'Name', 'File', 'Data', 'Expiry', 'Tags' ], $grid_data/*, $template*/);
            }
        }

        return $output;
    }
}
