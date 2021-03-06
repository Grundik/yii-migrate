<?php

namespace YiiMigrate;

use \Yii;

Yii::import('system.cli.commands.MigrateCommand');

class AMigrateCommand extends \MigrateCommand
{

    public $moduleMigrationPaths = 'application.{module}.migrations';
    public $module = null;
    public $config;
    public $environment;

    protected function getTemplate()
    {
        if ($this->templateFile !== null) {
            return file_get_contents($this->templateFile);
        } else {
            return parent::getTemplate();
        }
    }

    protected function _initModuleMigrationPaths(array $modules)
    {
        $paths = array();
        foreach ($modules as $module => $modulePath) {
            $path = Yii::getPathOfAlias(str_replace('{module}', $modulePath, $this->moduleMigrationPaths));
            if ($path === false || !is_dir($path)) {
                echo 'Warning: The module migration directory does not exist: ' . $path . "\n";
            } else {
                if (!defined('BASE_PATH')) {
                    $shortPath = $path;
                } else {
                    $shortPath = '...' . substr($path, strlen(BASE_PATH));
                }
                echo "Using module path: {$shortPath}" . PHP_EOL;
                $paths[$module] = $path;
            }
        }
        $this->moduleMigrationPaths = $paths;
    }

    private $isDbConnected = false;
    protected function getDbConnection()
    {
        $db = parent::getDbConnection();
        if ($this->isDbConnected) {
            return $db;
        }
        $this->isDbConnected = true;
        $app = Yii::app();
        if ($db && $app->migrateSchema) {
            echo "*** Switching to schema {$app->migrateSchema}\n";
            $schema = $db->quoteColumnName($app->migrateSchema);
            $db->createCommand("CREATE SCHEMA IF NOT EXISTS {$schema}")->execute();
            $db->createCommand("SET search_path TO {$schema}")->execute();
            $this->migrationTable = "{$app->migrateSchema}.{$this->migrationTable}";
        }
        if ($db && isset($app->prologCommands) && file_exists($app->prologCommands)) {
            $cmd = file_get_contents($app->prologCommands);
            if (false !== $cmd && '' !==$cmd) {
                echo "*** Applying prolog commands:\n$cmd\n";
                $db->createCommand($cmd)->execute();
                echo "*** Done\n\n";
            }
        }
        return $db;
    }

    public function beforeAction($action, $params)
    {
        $app = Yii::app();
        if (isset($app->migrateModules) && is_array($app->migrateModules)) {
            $this->_initModuleMigrationPaths($app->migrateModules);
        }
        return parent::beforeAction($action, $params);
    }

    public function afterAction($action, $params, $exitCode = 0)
    {
        $app = Yii::app();
        if (isset($app->epilogCommands) && file_exists($app->epilogCommands)) {
            $db = $this->getDbConnection();
            $cmd = file_get_contents($app->epilogCommands);
            if (false !== $cmd) {
                echo "*** Applying epilog commands:\n$cmd\n";
                $db->createCommand($cmd)->execute();
                echo "*** Done\n\n";
            }
        }
        return parent::afterAction($action, $params, $exitCode);
    }

    protected function _getModuleMigrations($module, array $applied)
    {
        $migrations = array();
        $modulePath = $this->_getModuleMigrationPath($module);
        $handle = opendir($modulePath);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..')
                continue;
            $path = $modulePath . DIRECTORY_SEPARATOR . $file;
            if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file($path) && !isset($applied[$module . ':' . $matches[2]]))
                $migrations[] = $module . ':' . $matches[1];
        }
        closedir($handle);
        sort($migrations);
        return $migrations;
    }

    protected function _getModuleMigrationPath($module)
    {
        if (!isset($this->moduleMigrationPaths[$module])) {
            $this->usageError('Module ' . $module . ' not defined or module migration path is not usable.');
        }
        return $this->moduleMigrationPaths[$module];
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
    protected function getMigrationHistory($limit, $modules = null)
    {
        $db = $this->getDbConnection();
        if ($db->schema->getTable($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $select = $db->createCommand()
                ->select('version, apply_time')
                ->from($this->migrationTable)
                ->order('version DESC')
                ->limit($limit);

        if (null === $modules && $this->module) {
            $modules = $this->module;
        }
        if (true === $modules) {
            $select->where("version LIKE '%:m%'");
        } elseif (is_string($modules)) {
            $select->where("version LIKE '{$modules}:m%'");
        } else {
            $select->where("version NOT LIKE '%:m%'");
        }
        return \CHtml::listData($select->queryAll(), 'version', 'apply_time');
    }

    public function actionCreate($args)
    {
        if (isset($args[1])) {
            $this->migrationPath = $this->_getModuleMigrationPath($args[1]);
        } elseif ($this->module) {
            $this->migrationPath = $this->_getModuleMigrationPath($this->module);
        }
        return parent::actionCreate($args);
    }

    protected function instantiateMigration($class)
    {
        if (preg_match('@^(.+):(.+)$@', $class, $matches)) {
            $class = $matches[2];
            $path = $this->_getModuleMigrationPath($matches[1]);
        } else {
            $path = $this->migrationPath;
        }
        $file = $path . DIRECTORY_SEPARATOR . $class . '.php';
        require_once($file);
        $migration = new $class;
        $migration->setDbConnection($this->getDbConnection());
        return $migration;
    }

    protected function getNewMigrations()
    {
        if (!$this->module) {
            $migrations = parent::getNewMigrations();
        } else {
            $migrations = array();
        }
        $module = $this->module ?: true;

        $applied = array();
        foreach ($this->getMigrationHistory(-1, $module) as $version => $time) {
            if (preg_match('@^(.+:)m(\d{6}_\d{6})_@', $version, $matches)) {
                $applied[$matches[1] . $matches[2]] = true;
            }
        }

        if (is_array($this->moduleMigrationPaths)) {
            foreach ($this->moduleMigrationPaths as $module => $modulePath) {
                $migrations = array_merge($migrations, $this->_getModuleMigrations($module, $applied));
            }
        }
        usort($migrations, function($a, $b) {
            if (preg_match('@m(\d{6}_\d{6})@', $a, $matches)) {
                $a = $matches[1];
            }
            if (preg_match('@m(\d{6}_\d{6})@', $b, $matches)) {
                $b = $matches[1];
            }
            if ($a < $b) {
                return -1;
            } elseif ($a > $b) {
                return 1;
            }
            return 0;
        });
        return $migrations;
    }

}
