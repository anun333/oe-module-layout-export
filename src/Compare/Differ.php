<?php

/**
 * Compares two snapshots: a file with this site, or a file with another file. It reports, without
 * changing anything:
 *  - layouts and lists only on one side;
 *  - in a layout, the form's and groups' properties and the fields, added, removed or changed, column by column;
 *  - in a list, its own row and options, added, removed or changed;
 *  - fields whose patient_data, employer_data or history_data column the other side lacks;
 *  - lists the fields use that neither the other side nor the file has;
 *  - columns one side's tables have and the other's don't (OpenEMR 7.0 lacks a few that 8.x has);
 *  - field types the other side doesn't know (8.0 added 55 and 56).
 * list_options.last_updated is a timestamp OpenEMR sets on every edit, so it's left out of the
 * comparison of values.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Compare;

use OpenHealthcare\LayoutExport\Snapshot\Snapshot;

final class Differ
{
    /** Columns whose values aren't compared. */
    private const IGNORED = ['list_options' => ['last_updated']];

    /**
     * @param Snapshot $a the file
     * @param Snapshot $b this site, or the other file
     * @param array<string, list<string>>|null $bColumns b's patient_data, employer_data and history_data columns, when b is a site
     * @param list<int>|null $bFieldTypes the field types b's OpenEMR knows, when b is a site
     * @param list<string>|null $bListIds every list b has, when b is a site (its snapshot holds only the file's lists)
     */
    public static function compare(Snapshot $a, Snapshot $b, ?array $bColumns = null, ?array $bFieldTypes = null, ?array $bListIds = null): Report
    {
        $a = $a->normalized();
        $b = $b->normalized();
        $r = new Report();
        foreach ($a->layouts as $id => $layout) {
            if (!isset($b->layouts[$id])) {
                $r->add(Report::ONLY_IN_A, "layout:$id", count($layout['fields']) . ' fields');
                continue;
            }
            self::rows($r, "layout:$id", 'group', $layout['properties'], $b->layouts[$id]['properties'], ['grp_group_id'], 'layout_group_properties', $a, $b);
            self::rows($r, "layout:$id", 'field', $layout['fields'], $b->layouts[$id]['fields'], ['field_id'], 'layout_options', $a, $b);
        }
        foreach ($b->layouts as $id => $layout) {
            if (!isset($a->layouts[$id])) {
                $r->add(Report::ONLY_IN_B, "layout:$id", count($layout['fields']) . ' fields');
            }
        }
        foreach ($a->lists as $id => $list) {
            if (!isset($b->lists[$id])) {
                $r->add(Report::ONLY_IN_A, "list:$id", count($list['options']) . ' options');
                continue;
            }
            self::rows($r, "list:$id", 'list', $list['list'] === null ? [] : [$list['list']], $b->lists[$id]['list'] === null ? [] : [$b->lists[$id]['list']],
                ['option_id'], 'list_options', $a, $b);
            self::rows($r, "list:$id", 'option', $list['options'], $b->lists[$id]['options'], ['option_id'], 'list_options', $a, $b);
        }
        foreach ($b->lists as $id => $list) {
            if (!isset($a->lists[$id])) {
                $r->add(Report::ONLY_IN_B, "list:$id", count($list['options']) . ' options');
            }
        }
        if ($bColumns !== null) {
            foreach ($a->needs() as $table => $columns) {
                foreach (array_diff($columns, $bColumns[$table] ?? []) as $column) {
                    $r->add(Report::MISSING_COLUMN, "$table.$column", 'a field needs it');
                }
            }
        }
        $bHas = array_fill_keys($bListIds ?? [], true);
        foreach ($a->listsUsed() as $id) {
            if (!isset($a->lists[$id]) && !isset($b->lists[$id]) && !isset($bHas[$id])) {
                $r->add(Report::MISSING_LIST, "list:$id", 'a field uses it');
            }
        }
        foreach (Snapshot::TABLES as $table) {
            $ac = $a->schema[$table] ?? [];
            $bc = $b->schema[$table] ?? [];
            if ($ac === [] || $bc === []) {
                continue;
            }
            foreach (array_diff($ac, $bc) as $column) {
                $r->add(Report::COLUMN_ONLY_IN_A, "$table.$column", '');
            }
            foreach (array_diff($bc, $ac) as $column) {
                $r->add(Report::COLUMN_ONLY_IN_B, "$table.$column", '');
            }
        }
        if ($bFieldTypes !== null) {
            foreach ($a->layouts as $id => $layout) {
                foreach ($layout['fields'] as $field) {
                    $type = (int)($field['data_type'] ?? 0);
                    if ($type !== 0 && !in_array($type, $bFieldTypes, true)) {
                        $r->add(Report::UNKNOWN_TYPE, "layout:$id field:" . $field['field_id'], "type $type");
                    }
                }
            }
        }
        return $r;
    }

    /**
     * @param list<array<string, ?string>> $as
     * @param list<array<string, ?string>> $bs
     * @param list<string> $key
     */
    private static function rows(Report $r, string $item, string $what, array $as, array $bs, array $key, string $table, Snapshot $a, Snapshot $b): void
    {
        $index = static function (array $rows) use ($key): array {
            $out = [];
            foreach ($rows as $row) {
                $out[implode("\x1F", array_map(static fn(string $k): string => (string)($row[$k] ?? ''), $key))] = $row;
            }
            return $out;
        };
        $ai = $index($as);
        $bi = $index($bs);
        $shared = array_values(array_intersect($a->schema[$table] ?? array_keys((array)reset($as)), $b->schema[$table] ?? array_keys((array)reset($bs))));
        $compared = array_diff($shared, self::IGNORED[$table] ?? []);
        foreach ($ai as $k => $row) {
            $label = $what === 'group' && $k === '' ? 'form properties' : "$what " . str_replace("\x1F", ' ', (string)$k);
            if (!isset($bi[$k])) {
                $r->add(Report::ROW_ONLY_IN_A, $item, $label);
                continue;
            }
            $changes = [];
            foreach ($compared as $column) {
                $x = $row[$column] ?? null;
                $y = $bi[$k][$column] ?? null;
                if ($x !== $y && !(is_numeric($x) && is_numeric($y) && (float)$x === (float)$y)) {
                    $changes[] = ['column' => $column, 'a' => $x, 'b' => $y];
                }
            }
            if ($changes !== []) {
                $r->add(Report::CHANGED, $item, $label, $changes);
            }
        }
        foreach ($bi as $k => $row) {
            if (!isset($ai[$k])) {
                $r->add(Report::ROW_ONLY_IN_B, $item, $what === 'group' && $k === '' ? 'form properties' : "$what " . str_replace("\x1F", ' ', (string)$k));
            }
        }
    }
}
