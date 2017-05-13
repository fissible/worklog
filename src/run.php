<?php
use Worklog\Application;
use Worklog\CommandLine\Output;

if (DEVELOPMENT_MODE && IS_CLI)
    Application::timer();
try {
    handle_result(App()->run());
} catch (Exception $e) {
    error_exit($e->getMessage());
}
if (DEVELOPMENT_MODE && IS_CLI) {
    printl(Output::color(
    	Application::timer().' seconds',
    	'dark_gray', 'black'
    ));
}
exit(0);
