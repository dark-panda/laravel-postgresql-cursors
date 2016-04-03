<?php

namespace DarkPanda\Laravel\Eloquent;

class PostgreSQLCursor implements \IteratorAggregate
{
    protected $query;
    protected $model;
    protected $cursorName;
    protected $connection;

    public function __construct($query)
    {
        $this->query = clone $query;
        $this->model = $query->getModel();
        $this->connection = $this->model->getConnection();
    }

    public function each(callable $callback = null)
    {
        foreach ($this as $model) {
            $callback($model);
        }
    }

    public function getIterator()
    {
        $cursorClosed = false;

        try {
            $this->connection->beginTransaction();
            $this->declareCursor();

            while ($row = $this->fetchForward()) {
                $model = $this->model->newFromBuilder($row);
                yield $model;
            }

            $this->closeCursor();
            $this->connection->commit();
            $cursorClosed = true;
        } finally {
            if (!$cursorClosed) {
                $this->connection->rollback();
            }
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
        $this->connection->statement("declare {$this->getCursorName()} cursor for {$this->query->toSql()}", $this->query->getBindings());
    }

    public function fetchForward()
    {
        return $this->connection->selectOne("fetch forward from {$this->getCursorName()}");
    }

    public function closeCursor()
    {
        $this->connection->unprepared("close {$this->getCursorName()}");
    }
}
