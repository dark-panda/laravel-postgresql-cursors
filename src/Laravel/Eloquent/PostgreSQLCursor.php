<?php

namespace DarkPanda\Laravel\Eloquent;

use Illuminate\Support\Facades\DB;

class PostgreSQLCursor implements \IteratorAggregate
{
    protected $query;
    protected $model;
    protected $cursorName;

    public function __construct($query)
    {
        $this->query = clone $query;
        $this->model = $query->getModel();
    }

    public function each(callable $callback = null)
    {
        foreach ($this as $model) {
            $callback($model);
        }
    }

    public function getIterator()
    {
        try {
            DB::beginTransaction();
            $this->declareCursor();

            while ($row = $this->fetchForward()) {
                $model = $this->model->newFromBuilder($row);
                yield $model;
            }

            $this->closeCursor();
            DB::commit();
        } catch (\Exception $error) {
            DB::rollback();

            throw $error;
        }
    }

    public function getCursorName()
    {
        if (!$this->cursorName) {
            $this->cursorName = 'cursor_' . str_random(16);
        }

        return $this->cursorName;
    }

    public function declareCursor()
    {
        DB::statement("declare {$this->getCursorName()} cursor for {$this->query->toSql()}", $this->query->getBindings());
    }

    public function fetchForward()
    {
        return DB::selectOne("fetch forward from {$this->getCursorName()}");
    }

    public function closeCursor()
    {
        DB::unprepared("close {$this->getCursorName()}");
    }
}
