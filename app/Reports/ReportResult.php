<?php

namespace App\Reports;

/**
 * What a report produced: a table, plus optional summary lines shown above it.
 */
final class ReportResult
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string|int|float|null>>  $rows  one value per column
     * @param  list<array{label: string, value: string}>  $summary
     */
    public function __construct(
        public readonly string $title,
        public readonly array $subtitle,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $summary = [],
    ) {}
}
