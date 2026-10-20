<?php

/**
 * What a compare found, as a list of findings, each with its kind, the layout, list, table or
 * column it concerns, and for a changed row each column's two values.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Compare;

final class Report
{
    public const ONLY_IN_A = 'only_in_a';
    public const ONLY_IN_B = 'only_in_b';
    public const ROW_ONLY_IN_A = 'row_only_in_a';
    public const ROW_ONLY_IN_B = 'row_only_in_b';
    public const CHANGED = 'changed';
    public const MISSING_COLUMN = 'missing_column';
    public const MISSING_LIST = 'missing_list';
    public const COLUMN_ONLY_IN_A = 'column_only_in_a';
    public const COLUMN_ONLY_IN_B = 'column_only_in_b';
    public const UNKNOWN_TYPE = 'unknown_type';

    /** Kinds that are about the two OpenEMR versions rather than the layouts and lists themselves. */
    public const SCHEMA_KINDS = [self::COLUMN_ONLY_IN_A, self::COLUMN_ONLY_IN_B];

    /** @var list<array{kind: string, item: string, what: string, changes: list<array{column: string, a: ?string, b: ?string}>}> */
    private array $findings = [];

    /** @param list<array{column: string, a: ?string, b: ?string}> $changes */
    public function add(string $kind, string $item, string $what, array $changes = []): void
    {
        $this->findings[] = ['kind' => $kind, 'item' => $item, 'what' => $what, 'changes' => $changes];
    }

    /** @return list<array{kind: string, item: string, what: string, changes: list<array{column: string, a: ?string, b: ?string}>}> */
    public function findings(): array
    {
        return $this->findings;
    }

    /** No difference in the layouts and lists themselves (version differences in the tables aside). */
    public function same(): bool
    {
        foreach ($this->findings as $f) {
            if (!in_array($f['kind'], self::SCHEMA_KINDS, true)) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string, int> kind => how many */
    public function counts(): array
    {
        $out = [];
        foreach ($this->findings as $f) {
            $out[$f['kind']] = ($out[$f['kind']] ?? 0) + 1;
        }
        return $out;
    }
}
