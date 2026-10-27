<?php

/**
 * Core issue #14742: deleting a field in OpenEMR's Layout Editor drops its table column when the column is empty
 * and not on edit_layout.php's reserved list, and core code that reads a stock column by name then fails ("Unknown
 * column 'ref_providerID'" on a new visit, months later). This compares the site with its own OpenEMR's stock schema:
 *  - a stock column that's gone: core code reading it fails; database.sql's definition puts it back;
 *  - a stock field on the layout whose column the editor would drop if the field were deleted now;
 *  - a stock field not on the layout, its column still there.
 * It changes nothing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Stock;

final class StockCheck
{
    public const DROPPED = 'dropped';
    public const WOULD_DROP = 'would_drop';
    public const NOT_ON_LAYOUT = 'not_on_layout';

    /**
     * @param array<string, list<string>>       $siteFields    field ids on the site's layouts, by layout
     * @param array<string, list<string>>       $siteColumns   the site's columns, by table
     * @param array<string, list<string>>       $emptyColumns  the site's columns empty by the Layout Editor's own test, by table
     * @param array<string, list<string>>|null  $reserved      Reserved::fromSource(), or null when it couldn't be read
     * @return list<array{kind: string, layout: string, table: string, field: string, definition: string}>
     */
    public static function findings(StockSchema $stock, array $siteFields, array $siteColumns, array $emptyColumns, ?array $reserved): array
    {
        $out = [];
        $seenDropped = [];
        foreach ($stock->fields as $layout => $fields) {
            $table = StockSchema::tableFor($layout);
            $columns = array_flip($siteColumns[$table] ?? []);
            $onLayout = array_flip($siteFields[$layout] ?? []);
            $empty = array_flip($emptyColumns[$table] ?? []);
            $kept = array_flip($reserved[$table] ?? []);
            foreach ($fields as $field) {
                $definition = $stock->columns[$table][$field] ?? '';
                $row = ['layout' => $layout, 'table' => $table, 'field' => $field, 'definition' => $definition];
                if (!isset($columns[$field])) {
                    // A history column is shared by every HIS layout: one finding per column.
                    if (!isset($seenDropped["$table.$field"])) {
                        $seenDropped["$table.$field"] = true;
                        $out[] = ['kind' => self::DROPPED] + $row;
                    }
                } elseif (!isset($onLayout[$field])) {
                    $out[] = ['kind' => self::NOT_ON_LAYOUT] + $row;
                } elseif ($reserved !== null && isset($empty[$field]) && !isset($kept[$field])) {
                    $out[] = ['kind' => self::WOULD_DROP] + $row;
                }
            }
        }
        $order = [self::DROPPED => 0, self::WOULD_DROP => 1, self::NOT_ON_LAYOUT => 2];
        usort($out, static fn(array $a, array $b): int => [$order[$a['kind']], $a['layout'], $a['field']] <=> [$order[$b['kind']], $b['layout'], $b['field']]);
        return $out;
    }

    /** The statement that puts a dropped stock column back with its stock definition, for an administrator to run. */
    public static function repair(string $table, string $definition): string
    {
        return 'ALTER TABLE `' . $table . '` ADD COLUMN ' . $definition . ';';
    }
}
