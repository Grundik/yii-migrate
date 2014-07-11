<?php
namespace YiiMigrate;

\Yii::import('system.cli.commands.MigrateCommand');

class AMigrateCommand extends \MigrateCommand {

  protected function getTemplate() {
    if ($this->templateFile !== null) {
      return file_get_contents($this->templateFile);
    } else {
      return parent::getTemplate();
    }
  }
}
