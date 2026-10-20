<?php

/**
 * Layouts and lists as rows, wherever they came from: this site, an exported file, or an old
 * backup.php SQL file. Rows are column => value maps, values as the database gives them
 * (strings, or null). The tables are OpenEMR's own:
 *  - a layout is its layout_group_properties rows (the form's own row has grp_group_id '') and
 *    its layout_options rows (one per field);
 *  - a list is its row in list_options under list_id 'lists' and its options (list_options
 *    rows under its own list_id).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Snapshot;

final class Snapshot
{
    /** The tables a snapshot holds rows of. */
    public const TABLES = ['layout_group_properties', 'layout_options', 'list_options'];

    /**
     * @param array<string, array{properties: list<array<string, ?string>>, fields: list<array<string, ?string>>}> $layouts by form id
     * @param array<string, array{list: ?array<string, ?string>, options: list<array<string, ?string>>}>           $lists   by list id
     * @param array<string, list<string>> $schema the columns the source's tables had, by table
     * @param string $openemr the OpenEMR version the rows came from, when known
     */
    public function __construct(
        public readonly array $layouts,
        public readonly array $lists,
        public readonly array $schema,
        public readonly string $openemr = '',
    ) {
    }

    /** Field types OpenEMR keeps in tables of their own (previous names, addresses, telecoms, related persons). */
    private const OWN_TABLE_TYPES = [52, 54, 55, 56];

    /**
     * The table columns the layouts' fields are saved in, where OpenEMR saves each field (as
     * demographics_save.php and the LBF form's save do): a DEM field, or a field of any layout whose
     * source is D, in a patient_data column, unless its id starts em_, when it's the employer_data
     * column without the prefix; a HIS field, or one whose source is H, in a history_data column.
     * Field types with tables of their own need no column. (backup.php's repair added a
     * patient_data column for every DEM field, em_ and list types included.)
     *
     * @return array{employer_data: list<string>, history_data: list<string>, patient_data: list<string>}
     */
    public function needs(): array
    {
        $needs = ['employer_data' => [], 'history_data' => [], 'patient_data' => []];
        foreach ($this->layouts as $formId => $layout) {
            foreach ($layout['fields'] as $field) {
                $id = (string)($field['field_id'] ?? '');
                $source = (string)($field['source'] ?? '');
                if ($id === '' || in_array((int)($field['data_type'] ?? 0), self::OWN_TABLE_TYPES, true)) {
                    continue;
                }
                $table = match (true) {
                    (string)$formId === 'DEM' => 'patient_data',
                    str_starts_with((string)$formId, 'HIS'), $source === 'H' => 'history_data',
                    $source === 'D' => 'patient_data',
                    default => null,
                };
                if ($table === 'patient_data' && str_starts_with($id, 'em_')) {
                    [$table, $id] = ['employer_data', substr($id, 3)];
                }
                if ($table !== null && $id !== '') {
                    $needs[$table][$id] = true;
                }
            }
        }
        $out = [];
        foreach ($needs as $table => $ids) {
            $list = array_map('strval', array_keys($ids));
            sort($list, SORT_STRING);
            $out[$table] = $list;
        }
        return $out;
    }

    /**
     * The lists the layouts' fields use (list_id and list_backup_id), sorted.
     *
     * @return list<string>
     */
    public function listsUsed(): array
    {
        $used = [];
        foreach ($this->layouts as $layout) {
            foreach ($layout['fields'] as $field) {
                foreach (['list_id', 'list_backup_id'] as $column) {
                    $id = trim((string)($field[$column] ?? ''));
                    if ($id !== '') {
                        $used[$id] = true;
                    }
                }
            }
        }
        $out = array_map('strval', array_keys($used));
        sort($out, SORT_STRING);
        return $out;
    }

    /** The same rows in a fixed order: layouts and lists by id, rows by their keys, columns by name. */
    public function normalized(): self
    {
        $layouts = [];
        foreach ($this->layouts as $id => $layout) {
            $layouts[(string)$id] = [
                'properties' => self::sortRows($layout['properties'], ['grp_group_id']),
                'fields' => self::sortRows($layout['fields'], ['group_id', 'seq', 'field_id']),
            ];
        }
        ksort($layouts, SORT_STRING);
        $lists = [];
        foreach ($this->lists as $id => $list) {
            $row = $list['list'];
            if ($row !== null) {
                ksort($row, SORT_STRING);
            }
            $lists[(string)$id] = ['list' => $row, 'options' => self::sortRows($list['options'], ['seq', 'option_id'])];
        }
        ksort($lists, SORT_STRING);
        $schema = [];
        foreach ($this->schema as $table => $columns) {
            $schema[$table] = array_values($columns);
        }
        ksort($schema, SORT_STRING);
        return new self($layouts, $lists, $schema, $this->openemr);
    }

    /**
     * @param list<array<string, ?string>> $rows
     * @param list<string> $keys sorted on these, numbers as numbers, then on every column for ties
     * @return list<array<string, ?string>>
     */
    private static function sortRows(array $rows, array $keys): array
    {
        foreach ($rows as &$row) {
            ksort($row, SORT_STRING);
        }
        unset($row);
        usort($rows, static function (array $a, array $b) use ($keys): int {
            foreach ($keys as $k) {
                $x = $a[$k] ?? '';
                $y = $b[$k] ?? '';
                $c = is_numeric($x) && is_numeric($y) ? (float)$x <=> (float)$y : strcmp((string)$x, (string)$y);
                if ($c !== 0) {
                    return $c;
                }
            }
            return strcmp(json_encode($a) ?: '', json_encode($b) ?: '');
        });
        return $rows;
    }
}
