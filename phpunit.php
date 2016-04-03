<?php

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/

require __DIR__ . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Set The Default Timezone
|--------------------------------------------------------------------------
|
| Here we will set the default timezone for PHP. PHP is notoriously mean
| if the timezone is not explicitly set. This will be used by each of
| the PHP date and date-time functions throughout the application.
|
*/

date_default_timezone_set('UTC');

Carbon\Carbon::setTestNow(Carbon\Carbon::now());

$capsule = new \Illuminate\Database\Capsule\Manager();

$capsule->addConnection([
  'driver'    => 'pgsql',
  'host'      => getenv('DB_HOST') ?: 'localhost',
  'database'  => getenv('DB_DATABASE') ?: 'laravel_postgresql_cursors',
  'username'  => getenv('DB_USERNAME'),
  'password'  => getenv('DB_PASSWORD'),
  'charset'   => 'utf8',
  'prefix'    => '',
]);

$capsule->bootEloquent();
$connection = $capsule->getConnection();

$cursorModelsTableExists = $connection
  ->table('information_schema.tables')
  ->where('table_name', '=', 'cursor_models')
  ->exists();

if (!$cursorModelsTableExists) {
  $blueprint = new Illuminate\Database\Schema\Blueprint('cursor_models', function($table) {
    $table->increments('id');
    $table->text('name');
    $table->timestampsTz();
  });

  $blueprint->create();
  $blueprint->build($connection, new Illuminate\Database\Schema\Grammars\PostgresGrammar());
}
