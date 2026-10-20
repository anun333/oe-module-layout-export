<?php

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Tests;

use OpenHealthcare\LayoutExport\Snapshot\Snapshot;

/** Small hand-made snapshots for the tests. */
final class Rows
{
    public const FIELD_COLUMNS = ['form_id', 'field_id', 'group_id', 'title', 'seq', 'data_type', 'list_id', 'list_backup_id', 'source'];
    public const LIST_COLUMNS = ['list_id', 'option_id', 'title', 'seq', 'last_updated'];

    /** @param array<string, ?string> $over */
    public static function field(string $form, string $id, int $seq, array $over = []): array
    {
        return $over + ['form_id' => $form, 'field_id' => $id, 'group_id' => '1', 'title' => ucfirst($id), 'seq' => (string)$seq,
            'data_type' => '2', 'list_id' => '', 'list_backup_id' => '', 'source' => 'F'];
    }

    /** @param array<string, ?string> $over */
    public static function option(string $list, string $id, int $seq, array $over = []): array
    {
        return $over + ['list_id' => $list, 'option_id' => $id, 'title' => ucfirst($id), 'seq' => (string)$seq, 'last_updated' => '2026-10-01 10:00:00'];
    }

    public static function snapshot(?array $fields = null, ?array $options = null, ?array $schema = null): Snapshot
    {
        $fields ??= [self::field('LBFtest', 'color', 1, ['data_type' => '1', 'list_id' => 'colors']), self::field('LBFtest', 'weight', 2)];
        $options ??= [self::option('colors', 'red', 1), self::option('colors', 'blue', 2)];
        return new Snapshot(
            ['LBFtest' => ['properties' => [['grp_form_id' => 'LBFtest', 'grp_group_id' => '', 'grp_title' => 'Test form']], 'fields' => $fields]],
            ['colors' => ['list' => self::option('lists', 'colors', 1, ['title' => 'Colors']), 'options' => $options]],
            $schema ?? ['layout_options' => self::FIELD_COLUMNS, 'list_options' => self::LIST_COLUMNS, 'layout_group_properties' => ['grp_form_id', 'grp_group_id', 'grp_title']],
            '8.4.1',
        );
    }
}
