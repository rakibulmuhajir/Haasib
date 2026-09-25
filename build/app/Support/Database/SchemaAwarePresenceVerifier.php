<?php

namespace App\Support\Database;

use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Makes exists/unique rules on schema-qualified tables query the right database session.
 *
 * Laravel splits a rule's table on its first dot into connection + table, so
 * Rule::exists('fuel.nozzles') means "table nozzles on connection fuel". config/database.php
 * defines connections literally named after the schemas, and each one is a separate
 * PostgreSQL session without the request's company context. Under enforced row level
 * security that session sees nothing, so every value was rejected ("The selected
 * nozzle_readings.0.nozzle_id is invalid") and no daily close could post.
 *
 * Fixing each rule one by one had already been done three times and missed the next one: the
 * daily close built its rules in a loop, where a text scan can't see them. Here, whatever
 * way a rule is written, a schema name before the dot is read as the schema it is: the
 * default connection, table "fuel.nozzles".
 */
class SchemaAwarePresenceVerifier extends DatabasePresenceVerifier
{
    /** The application's PostgreSQL schemas; a rule naming one means the schema, never a connection. */
    public const SCHEMAS = ['acct', 'auth', 'inv', 'pay', 'tax', 'fuel', 'hsp', 'crm', 'umrah', 'audit'];

    private ?string $schema = null;

    public function setConnection($connection)
    {
        if (in_array($connection, self::SCHEMAS, true)) {
            $this->schema = $connection;
            $this->connection = null;

            return;
        }

        $this->schema = null;
        parent::setConnection($connection);
    }

    protected function table($table)
    {
        $qualified = $this->schema !== null && ! str_contains($table, '.') ? $this->schema.'.'.$table : $table;

        return $this->db->connection($this->connection)->table($qualified)->useWritePdo();
    }
}
