
# [laravel-postgresql-cursors](http://github.com/dark-panda/laravel-postgresql-cursors)

This Laravel 5 extension allows you to loop through record sets using
PostgreSQL as an IteratorAggregate. This allows you to cut down memory usage
by only pulling in individual records on each loop rather than pulling
everything into memory all at once.

To use a cursor, use `cursor` rather than `get` when fetching records like so:

```php
class MyModel extends Model {
  use \DarkPanda\Laravel\PostgreSQLCursors;
}

$records = Model::cursor();

foreach ($records as $record) {
  // do some stuff!
}

// Or...

$records->each(function ($record) {
  // do some stuff!
});

// Or...

$records->each('dump');
```

## Notes

* The because PostgreSQL cursors must be wrapped in transactions, a new
  transaction will be used for the duration of the loop. On exceptions or
  errors, the transaction will be rolled back, while on success the transaction
  will be committed and the cursor closed.

* These cursors are currently very simple, and are fetch forward-only.
  You'll get a single record per iteration with no scrolling.

* Eager loading is not currently handled, as we'd need to grab those records
  within the cursor as one big set of JOINs and then sort them out into their
  individual models. This could possibly be added later.

* Most Eloquent scopes are supported. Probably.

## License

This Laravel extension is licensed under an MIT-style license. See the
+MIT-LICENSE+ file for details.
