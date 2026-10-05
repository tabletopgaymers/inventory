<?php

namespace App\Session;

use Illuminate\Database\QueryException;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Arr;
use PDOException;

class BaselineDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function performInsert($sessionId, $payload)
    {
        try {
            return $this->getQuery()->insert(Arr::set($payload, 'id', $sessionId));
        } catch (QueryException) {
            if ($this->performUpdate($sessionId, $payload) > 0) {
                return true;
            }

            // Zero affected rows can be a valid no-op after a concurrent insert.
            // Confirm the complete intended payload, not merely row existence.
            $saved = $this->getQuery()->find($sessionId);
            if ($saved !== null) {
                foreach ($payload as $column => $value) {
                    if (! property_exists($saved, $column)
                        || ($saved->$column === null) !== ($value === null)
                        || (string) $saved->$column !== (string) $value) {
                        throw new PDOException('Database session persistence failed.');
                    }
                }

                return true;
            }

            // Do not retain the caught exception or its private diagnostics.
            throw new PDOException('Database session persistence failed.');
        }
    }
}
