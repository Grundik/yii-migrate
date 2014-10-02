yii-migrate
===========

Simple package to add database migrations to project

Installation
------------

* Add to composer.json:
    <code class="json"><pre>
    "require": {
        "grundik/yii-migrate": "*@dev"
    },
    "repositories": [{
      "type": "vcs",
      "url": "https://github.com/Grundik/yii-migrate.git"
    }, {
      "type": "vcs",
      "url": "https://github.com/yiisoft/yii.git"
    }]
    </pre></code>

* Run composer: <pre>composer update</pre>

* Create configuration file:
    <pre>cp vendor/grundik/yii-migrate/config/migrations.php-default config/migrations.php</pre>

* Edit configuration file to fulfill your needs: set database credentials, migrations path

* Run
    * vendor/bin/migrate - to perform migrate;
    * vendor/bin/migrate create <name> - to create new migration.

See also: http://www.yiiframework.com/doc/guide/1.1/en/database.migration

Modules
-------

To use multiple migration folders (e.g. for multiple project modules), define them in migrations.php as explained in example config.

Use parameter --module=<module_name> to perform action in explicit module:
<pre>vendor/bin/migrate create migration_name --module=module_name</pre>
Migrate without parameters will apply all new migrations from all modules.

Module migrations are applied in order of their creation, regardless of module.
