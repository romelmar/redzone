<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize 90... account_numbers by removing the leading '9' and any zeros that follow it.
        // Only apply the update for safe values that do not collide with an existing normalized account_number.
        $rows = DB::select("SELECT COUNT(*) AS cnt FROM subscribers WHERE account_number REGEXP '^90+' AND TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) = ''");
        if ($rows[0]->cnt > 0) {
            throw new \RuntimeException('Cannot normalize account numbers because one or more prefixed values reduce to an empty string.');
        }

        $duplicateCandidates = DB::select(
            "SELECT COUNT(*) AS cnt FROM (\n" .
            "  SELECT normalized FROM (\n" .
            "    SELECT TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized\n" .
            "    FROM subscribers\n" .
            "    WHERE account_number REGEXP '^90+'\n" .
            "      AND TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) != ''\n" .
            "  ) AS t\n" .
            "  GROUP BY normalized HAVING COUNT(*) > 1\n" .
            ") AS dup"
        );
        if ($duplicateCandidates[0]->cnt > 0) {
            throw new \RuntimeException('Cannot normalize account numbers because multiple prefixed values map to the same normalized value.');
        }

        DB::statement(
            "UPDATE subscribers s\n" .
            "JOIN (\n" .
            "  SELECT account_number, TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) AS normalized\n" .
            "  FROM subscribers\n" .
            "  WHERE account_number REGEXP '^90+'\n" .
            "    AND TRIM(LEADING '0' FROM SUBSTR(account_number, 2)) != ''\n" .
            ") AS candidates ON s.account_number = candidates.account_number\n" .
            "LEFT JOIN subscribers existing ON existing.account_number = candidates.normalized\n" .
            "  AND existing.account_number NOT REGEXP '^90+'\n" .
            "SET s.account_number = candidates.normalized\n" .
            "WHERE existing.account_number IS NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be safely rolled back.');
    }
};
