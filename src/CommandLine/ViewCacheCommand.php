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
    public static $options = [
        'k' => ['req' => true, 'description' => 'Use jq to get the specified cached item property'],
        't' => ['req' => null, 'description' => 'Query by tags']
    ];
    public static $arguments = [ 'query' ];
    // public static $usage = '[] %s';
    public static $menu = false;

    public function run()
    {
        $output = "No cached data.";

        if ($this->option('t')) {
            $tags = (array) $this->getData('query');
        } elseif ($query = $this->getData('query')) {
            // wlog view-cache -k data ea2b2676c28c0db26d39331a336c6b92.json
            if ($keypath = $this->option('k')) {
                $Cache = App()->Cache();
                $file_type = $Cache->file_type();
                $filepath = null;

                if ($file_type !== 'array') {
                    $filepath = $Cache->filename($query);
                }

                if (! is_null($filepath) && false === strpos($filepath, $Cache->path())) {
                    $filepath = $Cache->path().'/'.$filepath;
                }


                if (substr($keypath, 0, 1) !== '.') {
                    $keypath = '.'.$keypath;
                }

                if (! is_null($filepath)) {
                    $set_to = BinaryCommand::collect_output();
                    $Command = new BinaryCommand([
                        'cat',  $filepath, '|', 'jq', escapeshellarg($keypath)
                    ]);

                    if ($output = $Command->run()) {
                        if (is_array($output) && isset($output[0])) {
                            $output = array_map(function($line) {
                                $line = json_decode($line);
                                if (false !== ($_unserialized = @unserialize($line))) {
                                    $line = $_unserialized;
                                }
                                return $line;
                            }, $output);
                        }
                    }

                    BinaryCommand::collect_output($set_to);
                }

                return $output;
            }
        }
        
        $grid_data = [];
        $registry = $this->App()->Cache()->registry();
        $template = null;

        if (count($registry)) {
            foreach ($registry as $cache_name => $CacheItem) {
                $path = $CacheItem->filepath();

                if (isset($tags) && ! empty($tags) && ! array_intersect($tags, $CacheItem->tags)) {
                    continue;
                }

                $data_out = '-';
                if (is_scalar($CacheItem->data)) {
                    $data_out = $CacheItem->data;
                } elseif (is_array($CacheItem->data)) {
                    $data_out = print_r($CacheItem->data, true);
                } elseif ($CacheItem->data instanceof \stdClass) {
                    $data_out = print_r(json_decode(json_encode($CacheItem->data), true), true);
                } else {
                    $data_out = get_class($CacheItem->data);
                }
                $data_out = preg_replace('/[\r\n\s]+/', ' ', $data_out);

                $grid_data[] = [
                    $CacheItem->name,
                    basename($path),
                    $data_out,
                    ($CacheItem->expiry > 0 ? date("Y-m-d g:i a", $CacheItem->expiry) : '-'),
                    implode(', ', $CacheItem->tags)
                ];
            }

            if (count($grid_data)) {
                $output = Output::data_grid([ 'name', 'file', 'data', 'expiry', 'tags' ], $grid_data, $template);
            }
        }

        return $output;
    }
}
