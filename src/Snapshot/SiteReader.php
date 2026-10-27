<?php

/**
 * Reads layouts and lists from this site's own tables, every column the tables have (they differ
 * between OpenEMR 7.0 and 8.x), and the patient_data and history_data columns, for the compare.
 * It only reads.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Snapshot;

use OpenEMR\Common\Database\QueryUtils;
use OpenHealthcare\LayoutExport\Stock\StockSchema;

final class SiteReader
{
    /**
     * Every layout: the form ids in layout_group_properties or layout_options, with the form's title.
     *
     * @return array<string, string> form id => title
     */
    public function layouts(): array
    {
        $out = [];
        foreach (QueryUtils::fetchRecords("SELECT DISTINCT `form_id` FROM `layout_options` ORDER BY `form_id`") as $r) {
            $out[(string)$r['form_id']] = (string)$r['form_id'];
        }
        foreach (QueryUtils::fetchRecords("SELECT `grp_form_id`, `grp_title` FROM `layout_group_properties` WHERE `grp_group_id` = '' ORDER BY `grp_form_id`") as $r) {
            $out[(string)$r['grp_form_id']] = trim((string)$r['grp_title']) !== '' ? (string)$r['grp_title'] : (string)$r['grp_form_id'];
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Every list, as Admin › Forms › Lists shows them: the options of list_id 'lists'.
     *
     * @return array<string, string> list id => title
     */
    public function lists(): array
    {
        $out = [];
        foreach (QueryUtils::fetchRecords("SELECT `option_id`, `title` FROM `list_options` WHERE `list_id` = 'lists' ORDER BY `option_id`") as $r) {
            $out[(string)$r['option_id']] = (string)$r['title'];
        }
        return $out;
    }

    /**
     * The chosen layouts and lists, and with $withUsedLists the lists their fields use too.
     *
     * @param list<string> $layoutIds
     * @param list<string> $listIds
     */
    public function snapshot(array $layoutIds, array $listIds, bool $withUsedLists, string $openemr = ''): Snapshot
    {
        $layouts = [];
        foreach (array_unique($layoutIds) as $id) {
            $properties = QueryUtils::fetchRecords('SELECT * FROM `layout_group_properties` WHERE `grp_form_id` = ?', [$id]);
            $fields = QueryUtils::fetchRecords('SELECT * FROM `layout_options` WHERE `form_id` = ?', [$id]);
            if ($properties === [] && $fields === []) {
                continue;
            }
            $layouts[$id] = ['properties' => self::rows($properties), 'fields' => self::rows($fields)];
        }
        $wanted = array_fill_keys($listIds, true);
        if ($withUsedLists) {
            foreach ((new Snapshot($layouts, [], []))->listsUsed() as $id) {
                $wanted[$id] = true;
            }
        }
        $lists = [];
        foreach (array_keys($wanted) as $id) {
            $id = (string)$id;
            $row = QueryUtils::fetchRecords("SELECT * FROM `list_options` WHERE `list_id` = 'lists' AND `option_id` = ?", [$id])[0] ?? null;
            $options = QueryUtils::fetchRecords('SELECT * FROM `list_options` WHERE `list_id` = ?', [$id]);
            if ($row === null && $options === []) {
                continue;
            }
            $lists[$id] = ['list' => $row === null ? null : self::row($row), 'options' => self::rows($options)];
        }
        $schema = [];
        foreach (Snapshot::TABLES as $table) {
            $schema[$table] = $this->columns($table);
        }
        return (new Snapshot($layouts, $lists, $schema, $openemr))->normalized();
    }

    /**
     * A table's column names, in the table's order.
     *
     * @return list<string>
     */
    public function columns(string $table): array
    {
        if (!in_array($table, [...Snapshot::TABLES, 'employer_data', 'history_data', 'patient_data'], true)) {
            throw new \InvalidArgumentException("Not a table this module reads: $table");
        }
        return array_map(static fn(array $r): string => (string)$r['Field'], QueryUtils::fetchRecords("SHOW COLUMNS FROM `$table`"));
    }

    /**
     * The field ids on each of the given layouts.
     *
     * @param list<string> $formIds
     * @return array<string, list<string>> form id => field ids
     */
    public function layoutFields(array $formIds): array
    {
        $out = array_fill_keys($formIds, []);
        if ($formIds === []) {
            return $out;
        }
        foreach (QueryUtils::fetchRecords(
            'SELECT `form_id`, `field_id` FROM `layout_options` WHERE `form_id` IN (' . implode(', ', array_fill(0, count($formIds), '?')) . ')',
            $formIds
        ) as $r) {
            $out[(string)$r['form_id']][] = (string)$r['field_id'];
        }
        return $out;
    }

    /**
     * A table's column names from information_schema; none when the table isn't there (the IPPF tables, on most sites).
     *
     * @return list<string>
     */
    public function tableColumns(string $table): array
    {
        if (!in_array($table, StockSchema::TABLES, true)) {
            throw new \InvalidArgumentException("Not a table this module reads: $table");
        }
        return array_map(static fn(array $r): string => (string)$r['COLUMN_NAME'], QueryUtils::fetchRecords(
            'SELECT `COLUMN_NAME` FROM information_schema.COLUMNS WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? ORDER BY `ORDINAL_POSITION`',
            [$table]
        ));
    }

    /**
     * The columns with no value by the Layout Editor's own test before it drops one (no row where the column IS NOT
     * NULL AND != ''), all read in one pass over the table.
     *
     * @param list<string> $columns
     * @return list<string>
     */
    public function emptyColumns(string $table, array $columns): array
    {
        if (!in_array($table, StockSchema::TABLES, true)) {
            throw new \InvalidArgumentException("Not a table this module reads: $table");
        }
        $columns = array_values(array_filter($columns, static fn(string $c): bool => preg_match('/^[A-Za-z0-9_]+$/', $c) === 1));
        if ($columns === []) {
            return [];
        }
        $select = implode(', ', array_map(static fn(int $i, string $c): string => "MAX(CASE WHEN `$c` IS NOT NULL AND `$c` != '' THEN 1 ELSE 0 END) AS `v$i`", array_keys($columns), $columns));
        $row = QueryUtils::fetchRecords("SELECT $select FROM `$table`")[0] ?? [];
        $out = [];
        foreach ($columns as $i => $c) {
            if ((int)($row["v$i"] ?? 0) === 0) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, ?string>>
     */
    private static function rows(array $rows): array
    {
        return array_map(self::row(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, ?string>
     */
    private static function row(array $row): array
    {
        $out = [];
        foreach ($row as $column => $value) {
            if (is_string($column)) {
                $out[$column] = $value === null ? null : (string)$value;
            }
        }
        return $out;
    }
}
