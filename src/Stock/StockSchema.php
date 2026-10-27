<?php

/**
 * Core's stock layout fields and their table columns, read from the sql/database.sql of the OpenEMR this module runs
 * in, so the stock list is that version's own. Only the layouts whose fields are columns of a table count: the ones
 * OpenEMR's Layout Editor adds a column for, and drops the column of when a field is deleted (core issue #14742),
 * mapped as edit_layout.php's tableNameFromLayout() maps them. The file is read as data; nothing in it is run.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Stock;

final class StockSchema
{
    /** The tables the Layout Editor's table-backed layouts store their fields in. */
    public const TABLES = ['patient_data', 'history_data', 'lists_ippf_srh', 'lists_ippf_con', 'lists_ippf_gcac'];

    /**
     * @param array<string, list<string>>           $fields  stock field ids by layout (form_id), only those that are columns of the layout's table
     * @param array<string, array<string, string>>  $columns stock column definitions by table, as database.sql writes them
     */
    public function __construct(public readonly array $fields, public readonly array $columns)
    {
    }

    /** The table a layout stores its fields in, as tableNameFromLayout() maps it; '' for a layout with none. */
    public static function tableFor(string $formId): string
    {
        if (str_starts_with($formId, 'LBF') || str_starts_with($formId, 'LBT') || $formId === 'FACUSR') {
            return '';
        }
        return match (true) {
            $formId === 'DEM' => 'patient_data',
            str_starts_with($formId, 'HIS') => 'history_data',
            $formId === 'SRH' => 'lists_ippf_srh',
            $formId === 'CON' => 'lists_ippf_con',
            $formId === 'GCA' => 'lists_ippf_gcac',
            default => '',
        };
    }

    public static function fromSql(string $sql): self
    {
        $columns = [];
        $layout = [];
        foreach (self::statements($sql) as $statement) {
            $s = trim($statement);
            if (preg_match('/^CREATE TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*)\)[^)]*$/is', $s, $m) === 1 && in_array($m[1], self::TABLES, true)) {
                $columns[$m[1]] = self::columns($m[2]);
            } elseif (preg_match("/^INSERT INTO\s+`?layout_options`?\s*\([^)]*\)\s*VALUES\s*\(\s*'([A-Za-z0-9_]+)'\s*,\s*'([A-Za-z0-9_]+)'/is", $s, $m) === 1) {
                $layout[$m[1]][] = $m[2];
            }
        }
        $fields = [];
        foreach ($layout as $formId => $ids) {
            $table = self::tableFor($formId);
            if ($table === '' || !isset($columns[$table])) {
                continue;
            }
            $fields[$formId] = array_values(array_unique(array_filter($ids, static fn(string $id): bool => isset($columns[$table][$id]))));
        }
        return new self($fields, $columns);
    }

    /**
     * database.sql's statements: whole-line comments (--, #) dropped, each statement ending with a semicolon at the
     * end of a line, as the file is written. Comments there can hold an apostrophe, so quotes aren't tracked across lines.
     *
     * @return list<string>
     */
    private static function statements(string $sql): array
    {
        $out = [];
        $current = '';
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $t = trim($line);
            if ($current === '' && ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#'))) {
                continue;
            }
            $current .= $line . "\n";
            if (str_ends_with($t, ';')) {
                $out[] = substr(rtrim($current), 0, -1);
                $current = '';
            }
        }
        return $out;
    }

    /**
     * Each column definition in a CREATE TABLE body, by column name; keys and other clauses are left out.
     *
     * @return array<string, string>
     */
    private static function columns(string $body): array
    {
        $out = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];
            if ($quote !== null) {
                $current .= $c;
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                self::column(trim($current), $out);
                $current = '';
                continue;
            }
            $current .= $c;
        }
        self::column(trim($current), $out);
        return $out;
    }

    /** @param array<string, string> $out */
    private static function column(string $definition, array &$out): void
    {
        if (preg_match('/^`([A-Za-z0-9_]+)`\s+\S/', $definition, $m) === 1) {
            $out[$m[1]] = (string)preg_replace('/\s+/', ' ', $definition);
        }
    }
}
