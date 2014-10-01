<?php
namespace YiiMigrate;
use \Yii;

Yii::import('system.cli.commands.MigrateCommand');

class AMigrateCommand extends \MigrateCommand {

  public $moduleMigrationPaths = 'application.{module}.migrations';
  public $module = null;

  protected function getTemplate() {
    if ($this->templateFile !== null) {
      return file_get_contents($this->templateFile);
    } else {
      return parent::getTemplate();
    }
  }

  protected function _initModuleMigrationPaths(array $modules) {
    $paths = array();
    foreach ($modules as $module=>$modulePath) {
      $path = Yii::getPathOfAlias(str_replace('{module}', $modulePath, $this->moduleMigrationPaths));
      if ($path === false || !is_dir($path)) {
        echo 'Warning: The module migration directory does not exist: ' . $path . "\n";
      } else {
        echo "Using module path: {$path}" . PHP_EOL;
        $paths[$module] = $path;
      }
    }
    $this->moduleMigrationPaths = $paths;
  }

  public function beforeAction($action, $params) {
    $app = Yii::app();
    if (isset($app->migrateModules) && is_array($app->migrateModules)) {
      $this->_initModuleMigrationPaths($app->migrateModules);
    }
    return parent::beforeAction($action, $params);
  }

  protected function _getModuleMigrations($module, array $applied) {
    $migrations = array();
    $modulePath = $this->moduleMigrationPaths[$module];
    $handle = opendir($modulePath);
    while (($file = readdir($handle)) !== false) {
      if ($file === '.' || $file === '..')
        continue;
      $path = $modulePath . DIRECTORY_SEPARATOR . $file;
      if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file($path) && !isset($applied[$module.':'.$matches[2]]))
        $migrations[] = $module.':'.$matches[1];
    }
    closedir($handle);
    sort($migrations);
    return $migrations;
  }

  /**
   *
   * @param type $limit
   * @param mixed $modules модули, по которым смотреть миграции:
   *   true — все
   *   false — только базовый
   *   null — дефолтный (назначенный параметром --module либо базовый)
   * @return type
   */
  protected function getMigrationHistory($limit, $modules=null) {
    $db = $this->getDbConnection();
    if ($db->schema->getTable($this->migrationTable, true) === null) {
      $this->createMigrationHistoryTable();
    }
    $select = $db->createCommand()
                 ->select('version, apply_time')
                 ->from($this->migrationTable)
                 ->order('version DESC')
                 ->limit($limit);

    if (null===$modules && $this->module) {
      $modules = $this->module;
    }
    if (true===$modules) {
      $select->where("version LIKE '%:m%'");
    } elseif (is_string($modules)) {
      $select->where("version LIKE '{$modules}:m%'");
    } else {
      $select->where("version NOT LIKE '%:m%'");
    }
    return \CHtml::listData($select->queryAll(), 'version', 'apply_time');
  }

  protected function instantiateMigration($class) {
    if (preg_match('@^(.+):(.+)$@', $class, $matches)) {
      if (!isset($this->moduleMigrationPaths[$matches[1]])) {
        $this->usageError('Cannot instantiate module migration '.$class);
      }
      $class = $matches[2];
      $path = $this->moduleMigrationPaths[$matches[1]];;
    } else {
      $path = $this->migrationPath;
    }
    $file = $path . DIRECTORY_SEPARATOR . $class . '.php';
    require_once($file);
    $migration = new $class;
    $migration->setDbConnection($this->getDbConnection());
    return $migration;
  }

  protected function getNewMigrations() {
    $migrations = parent::getNewMigrations();

    $applied=array();
    foreach($this->getMigrationHistory(-1, true) as $version=>$time) {
      if (preg_match('@^(.+:)m(\d{6}_\d{6})_@', $version, $matches)) {
        $applied[$matches[1].$matches[2]] = true;
      }
    }

    if (is_array($this->moduleMigrationPaths)) {
      foreach ($this->moduleMigrationPaths as $module=>$modulePath) {
        $migrations = array_merge($migrations, $this->_getModuleMigrations($module, $applied));
      }
    }
    return $migrations;
  }

  public function actionCreate($args) {
    if (isset($args[1])) {
      if (!isset($this->moduleMigrationPaths[$args[1]])) {
        $this->usageError('Module '.$args[1].' not defined or module migration path is not usable.');
      }
      $this->migrationPath = $this->moduleMigrationPaths[$args[1]];
    }
    return parent::actionCreate($args);
  }
}
