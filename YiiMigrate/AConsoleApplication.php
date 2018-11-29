<?php

namespace YiiMigrate;

use \Yii;

class AConsoleApplication extends \CConsoleApplication {
  public $migrateModules;
  public $migrateSchema;
  public $epilogCommands;
  public $prologCommands;
}
