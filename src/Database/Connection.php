<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/11/17
 * Time: 2:22 PM
 */

namespace Worklog\Database;

use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

class Connection
{
    public function __construct($config = []) {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config, 'default');
        $this->capsule->setEventDispatcher(new Dispatcher(new Container));
        $this->capsule->bootEloquent();
        $this->capsule->setAsGlobal();
        $this->connection = $this->capsule->getConnection('default');
    }
}