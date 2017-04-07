<?php
return [
    'Redis' => [
        'database' => (int)getenv('REDIS_DATABASE_INDEX'),
        'prefix' => basename(__FILE__) . ':'
    ],
    'Sqlite' => [
        'path' => getenv('SQLITE_DATABASE_PATH') ?: APPLICATION_PATH.'/database/worklog.sqlite.db'
    ]
];