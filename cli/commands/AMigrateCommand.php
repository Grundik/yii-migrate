<?php

\Yii::import('system.cli.commands.MigrateCommand');

class AMigrateCommand extends MigrateCommand {

  public $templateExt = 'phptpl';

  protected function getTemplate() {
    if ($this->templateFile !== null && $this->templateExt !== null) {
      return file_get_contents(Yii::getPathOfAlias($this->templateFile) . '.' . $this->templateExt);
    } else {
      return parent::getTemplate();
    }
  }
}
