<?php

namespace Worklog\CommandLine;

use Worklog\Services\Git;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class GitCommand extends BinaryCommand
{
    public static $description = 'Run composer';

    public static $options = [
        'p' => ['req' => null, 'description' => 'Push the commit after committing it.'],
        'a' => ['req' => null, 'description' => 'Annotated tag'],
        'd' => ['req' => null, 'description' => 'Delete a tag'],
        'l' => ['req' => null, 'description' => 'Search for tags with a particular pattern'],
        'm' => ['req' => true, 'description' => 'Message text'],
        'r' => ['req' => null, 'description' => 'Random flag.'],
    ];

    public static $arguments = [ 'subcommand' ];

    protected $subcommands;
    

    public function init()
    {
        if (! $this->initialized()) {
            $this->setBinary(env('BINARY_GIT'));
            $this->registerSubcommand('branch');
            $this->registerSubcommand('commit');    // git commit -m <prompt> [&& git push]
            $this->registerSubcommand('diff');      // show changed files
            $this->registerSubcommand('status');    // lists changed files

            $this->registerSubcommand('tag');       // create/query git repo tags
            $this->registerSubcommand('revision');  // show current commit hash

            parent::init();
        }
    }

    public function run()
    {
        $this->init();

        if ($subcommand = $this->argument('subcommand')) {
            return $this->runSubcommand($subcommand);
        }
        
        return parent::run();
    }

    /*------------------------------------------*\
      subcommand implementations _{subcommand}()
    \*------------------------------------------*/

    protected function _branch($name = null)
    {
        // return Git::status($short);
    }

    protected function _revision()
    {
        return Git::hash('HEAD');
    }

    protected function _diff()
    {
        // return Git::diff($this->arguments('diff'));
        $args = $this->arguments();
        $args[] = '|';
        $args[] = APPLICATION_PATH.'/diff-lines';
        static::collect_output();
        $output = static::call($args);
        $last_file = null;

        foreach ($output as $key => $line) {
            list($file, $line_num, $line) = explode(":", $line, 3);
            $first_char = substr($line, 0, 1);

            if ($first_char == '+') {
                $line = Output::color($line, 'light_green');
            }
            if ($first_char == '-') {
                $line = Output::color($line, 'red');
            }
            
            if (is_null($last_file) || $last_file !== $file) {
                $last_file = $file;
                $colored_filed = Output::color($file, 'cyan');
                printl("\n".$colored_filed);

            }
            printl(sprintf("  %s%s",
                mb_str_pad(Output::color($line_num, 'dark_gray'), 16, ' ', STR_PAD_RIGHT),
                $line
            ));
        }
    }

    /**
     * Stage all files and commit to git repository
     */
    protected function _commit()
    {
        // get last commit message
        $commit_message = coalesce($this->getData('message'), $this->flag('m'), Git::commit_message('HEAD'));

        // cUrl silly commit message
        if ($this->flag('r') && $output = $this->getRandomCommitMessage()) {
            $commit_message = $output;
        }

        if (IS_CLI) {
            if ($input = Input::ask('Commit message'.($commit_message ? ' ('.$commit_message.')' : '').': ', $commit_message)) {
                $input = trim($input);
                if (strlen($input)) {
                    $commit_message = $input;
                }
            }
        }
        
        Git::commit($commit_message, true);

        if ($this->flag('p')) {
            Git::push();
        }
    }

    protected function _status($short = true)
    {
        return Git::status($short);
    }

    /**
     *
     */
    protected function _tag()
    {
        Git::fetch(true);

        $arguments = $this->arguments('tag');
        $command = [ 'tag' ];

        $annotate = $this->flag('a') || ($this->flag('m') && (false === $this->flag('s') && false === $this->flag('u')));
        $delete = $this->flag('d');
        $lookup = ($this->flag('l') || $this->flag('n') || $this->flag('sort') || $this->flag('format'));

        switch (true) {
            case (false !== $annotate):
                $command[] = '-a';
                if ($tag = unwrap($arguments)) {
                    $command[] = $tag;
                }
                if ($message = $this->flag('m')) {
                    $command[] = '-m '.escapeshellarg($message);
                }
                break;
            case (false !== $delete):
                $command[] = '-d';
                if ($tag = unwrap($arguments)) {
                    $command[] = $tag;
                }
                break;
            default:
                foreach ($this->flags() as $key => $flag) {
                    $command[] = '-'.ltrim($flag, '-');
                }
                foreach ($arguments as $key => $argument) {
                    $command[] = $argument;
                }
                break;
        }

        // git tag v1.4-lw                          - lightweight tag
        // git tag -a v1.4 -m "my version 1.4"      - annotated tag

        // git tag [-a | -s | -u <keyid>] [-f] [-m <msg> | -F <file>] <tagname> [<commit> | <object>]
        // git tag -d <tagname>...                  - delete tag

        // If -m <msg> or -F <file> is given and -a, -s, and -u <keyid> are absent, -a is implied.


        if (IS_CLI) {
            if ($annotate && ! isset($message)) {
                $prompt = 'Annotated tag description'.($this->flag('a') ? '' :' (optional)').': ';
                if ($input = Input::ask($prompt)) {
                    $message = trim($input);
                    if (strlen($message)) {
                        $command[] = '-m '.escapeshellarg($message);
                    }
                }
            }
        }

        return $this->call($command);
    }

    private function getRandomCommitMessage()
    {
        return unwrap(BinaryCommand::call('curl -vs http://whatthecommit.com/index.txt 2> /dev/null'));
    }
}
