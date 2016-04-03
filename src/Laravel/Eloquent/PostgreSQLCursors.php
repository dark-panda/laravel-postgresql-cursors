<?php

namespace DarkPanda\Laravel\Eloquent;

trait PostgreSQLCursors
{
    public function scopeCursor($query)
    {
        return new PostgreSQLCursor($query);
    }
}
