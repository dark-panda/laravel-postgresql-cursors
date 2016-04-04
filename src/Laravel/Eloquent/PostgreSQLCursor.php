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

    public function each()
    {
        $count = 1;
        $callback = null;
        $args = func_get_args();

        if (count($args) > 1) {
            list($count, $callback) = $args;
        } else {
            list($callback) = $args;
        }

        if ($count < 1) {
            throw new \InvalidArgumentException('$count should be > 0.');
        }

        if ($count > 1) {
            foreach ($this->iterate($count) as $models) {
                $callback($models);
            }
            return;
        }

        foreach ($this as $model) {
            $callback($model);
        }
    }

    public function getIterator()
    {
        foreach ($this->iterate() as $rows) {
            yield $rows[0];
        }
    }

    public function iterate($count = 1)
    {
        $cursorClosed = false;

        try {
            $this->connection->beginTransaction();
            $this->declareCursor();

            while ($rows = $this->fetchForward($count)) {
                // var_dump($rows);

                $models = array_map(function ($row) {
                    return $this->model->newFromBuilder($row);
                }, $rows);

                yield $models;
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

    public function fetchForward($count = 1)
    {
        return $this->connection->select(sprintf("fetch forward %d from {$this->getCursorName()}", $count));
    }

    public function closeCursor()
    {
        $this->connection->unprepared("close {$this->getCursorName()}");
    }
}
