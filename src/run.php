<?php
use Worklog\Application;
use Worklog\CommandLine\Output;

if (DEVELOPMENT_MODE && IS_CLI)
    Application::timer();
try {
    $result = App()->run();
    show_errors();
    handle_result($result);
} catch (Exception $e) {
    error_exit(Output::color($e->getMessage(), 'red'));
}
if (DEVELOPMENT_MODE && IS_CLI) {
    print "\n".Application::timer().' seconds'."\n";
    print "Done.\n";
}
exit(0);
