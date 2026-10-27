<?php

/**
 * The columns OpenEMR's Layout Editor never drops, read from the site's own interface/super/edit_layout.php
 * (isColumnReserved(), a hand-kept list per table), so the list is that version's own. Read as text; never run.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Stock;

final class Reserved
{
    /**
     * The reserved columns by table, or null when the function can't be read: a list must hold patient_data's pid,
     * which every version reserves, or the reading is taken as failed rather than as "nothing reserved".
     *
     * @return array<string, list<string>>|null
     */
    public static function fromSource(string $php): ?array
    {
        if (preg_match('/function isColumnReserved\s*\((.*?)\n\}/s', $php, $m) !== 1) {
            return null;
        }
        $out = [];
        preg_match_all("/\\\$tablename\s*==\s*'([A-Za-z0-9_]+)'\s*\)\s*\{\s*if\s*\(\s*in_array\(\s*\\\$field_id\s*,\s*(?:array\(|\[)(.*?)(?:\)|\])\s*\)/s", $m[1], $blocks, PREG_SET_ORDER);
        foreach ($blocks as $b) {
            preg_match_all("/'([A-Za-z0-9_]+)'/", $b[2], $ids);
            $out[$b[1]] = array_values(array_unique($ids[1]));
        }
        return in_array('pid', $out['patient_data'] ?? [], true) ? $out : null;
    }
}
