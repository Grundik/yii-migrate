<?php

$currentpath = dirname(__FILE__);
$loader = null;
$basepath = null;

if (getenv('BASE_PATH')) {
    define('BASE_PATH', getenv('BASE_PATH'));
}
if (!defined('BASE_PATH')) {
  $basepath = $currentpath;
  /* @var $loader \Composer\Autoload\ClassLoader */
  $loader = null;
  $i = 4;
  while (!file_exists($basepath.'/vendor/autoload.php') && $i-->0) {
    $basepath = $basepath.'/..';
  }
  if ($i) {
    $basepath = realpath($basepath);
  }
  define('BASE_PATH', $basepath);
} else {
  $basepath = BASE_PATH;
}

if ($basepath) {
  $loader = @include($basepath . '/vendor/autoload.php');
}
if  (!$loader) {
  throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}
$loader->add('YiiMigrate\\', __DIR__);

$opts = getopt('', ['config:', 'environment:']);

$env = null;
if (isset($opts['environment'])) {
    $env = $opts['environment'];
} else {
    $env = getenv('YIIMIGRATE_ENV');
}
if ($env) {
  $env = "-{$env}";
} else {
  $env = '';
}

$configPath = null;
if (isset($opts['config'])) {
    $configPath = $opts['config'];
} else {
    $configPath = "{$basepath}/config/migration{$env}.php";
}
$config = @include($configPath);
if (!$config) {
  throw new RuntimeException("Migration config in {$configPath} not found, please use {$currentpath}/config/migration.php-default to create one");
}

$config['commandMap'] = array(
  'migrate'=>array(
    'class'         =>'\YiiMigrate\AMigrateCommand',
    'migrationPath' =>'application.migrations',
    'migrationTable'=>'tbl_migration',
    'connectionID' =>'db',
    'templateFile' => isset($config['templateFile'])
                       ? $basepath.'/'.$config['templateFile']
                       : $currentpath.'/migrations/template.phptpl',
    //'templateExt'  => 'phptpl',
    //'epilogCommands' => null,
    //'prologCommands' => null,
  )
);
$config['basePath'] = $basepath.'/'.(isset($config['migrationsPath'])?$config['migrationsPath']:'.');
unset($config['migrationsPath']);
unset($config['templateFile']);

defined('STDIN') || define('STDIN', fopen('php://stdin', 'r'));

defined('YII_DEBUG') || define('YII_DEBUG',true);

require_once ($basepath . '/vendor/yiisoft/yii/framework/yii.php');
$loader->setUseIncludePath($basepath.'/cli/commands');

$app = Yii::createApplication('YiiMigrate\AConsoleApplication', $config);
$app->commandRunner->addCommands($currentpath.'/cli/commands');

array_splice($_SERVER['argv'], 1, 0, 'migrate');
$app->run();
