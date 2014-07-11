<?php

$currentpath = dirname(__FILE__);
$basepath = $currentpath;
/* @var $loader \Composer\Autoload\ClassLoader */
$loader = null;
$i = 4;
while (!file_exists($basepath.'/vendor/autoload.php') && $i-->0) {
  $basepath = $basepath.'/..';
}
if ($i) {
  $basepath = realpath($basepath);
  $loader = @include($basepath . '/vendor/autoload.php');
}

if  (!$loader) {
  throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}
$loader->add('YiiMigrate\\', __DIR__);

$config = @include($basepath . '/config/migration.php');
if (!$config) {
  throw new RuntimeException("Migration config in /config/migration.php not found, please use {$basepath}/config/migration.php-default to create one");
}

$config['commandMap'] = array(
  'migrate'=>array(
    'class'         =>'\YiiMigrate\AMigrateCommand',
    'migrationPath' =>'application.migrations',
    'migrationTable'=>'tbl_migration',
    'connectionID' =>'db',
    'templateFile' => $currentpath.'/migrations/template.phptpl', //'application.migrations.template',
    //'templateExt'  => 'phptpl',
  )
);
$config['basePath'] = $basepath.'/'.(isset($config['migrationsPath'])?$config['migrationsPath']:'.');
unset($config['migrationsPath']);

defined('STDIN') || define('STDIN', fopen('php://stdin', 'r'));

defined('YII_DEBUG') || define('YII_DEBUG',true);

require_once ($basepath . '/vendor/yiisoft/yii/framework/yii.php');
$loader->setUseIncludePath($basepath.'/cli/commands');

$app=Yii::createConsoleApplication($config);
$app->commandRunner->addCommands($currentpath.'/cli/commands');

array_splice($_SERVER['argv'], 1, 0, 'migrate');
$app->run();
