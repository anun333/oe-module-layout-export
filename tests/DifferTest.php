<?php

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Tests;

use OpenHealthcare\LayoutExport\Compare\Differ;
use OpenHealthcare\LayoutExport\Compare\Report;
use OpenHealthcare\LayoutExport\Snapshot\Snapshot;
use PHPUnit\Framework\TestCase;

final class DifferTest extends TestCase
{
    private const COLUMNS = ['employer_data' => ['pid', 'name'], 'history_data' => ['pid'], 'patient_data' => ['pid', 'fname']];

    public function testTheSameRowsAreTheSame(): void
    {
        $r = Differ::compare(Rows::snapshot(), Rows::snapshot(), self::COLUMNS, [1, 2]);
        $this->assertSame([], $r->findings());
        $this->assertTrue($r->same());
    }

    public function testOneChangedFieldAndOneChangedOptionAreExactlyWhatIsReported(): void
    {
        $file = Rows::snapshot(
            [Rows::field('LBFtest', 'color', 1, ['data_type' => '1', 'list_id' => 'colors']), Rows::field('LBFtest', 'weight', 2, ['title' => 'Weight (lb)'])],
            [Rows::option('colors', 'red', 1, ['title' => 'Crimson']), Rows::option('colors', 'blue', 2)],
        );
        $r = Differ::compare($file, Rows::snapshot(), self::COLUMNS, [1, 2]);
        $this->assertSame([
            ['kind' => Report::CHANGED, 'item' => 'layout:LBFtest', 'what' => 'field weight', 'changes' => [['column' => 'title', 'a' => 'Weight (lb)', 'b' => 'Weight']]],
            ['kind' => Report::CHANGED, 'item' => 'list:colors', 'what' => 'option red', 'changes' => [['column' => 'title', 'a' => 'Crimson', 'b' => 'Red']]],
        ], $r->findings());
        $this->assertFalse($r->same());
    }

    public function testAddedAndRemovedRowsAndWholeItems(): void
    {
        $file = Rows::snapshot([Rows::field('LBFtest', 'color', 1, ['data_type' => '1', 'list_id' => 'colors']), Rows::field('LBFtest', 'height', 3)],
            [Rows::option('colors', 'red', 1)]);
        $kinds = array_map(static fn(array $f): string => $f['kind'] . ' ' . $f['what'], Differ::compare($file, Rows::snapshot())->findings());
        $this->assertSame([Report::ROW_ONLY_IN_A . ' field height', Report::ROW_ONLY_IN_B . ' field weight', Report::ROW_ONLY_IN_B . ' option blue'], $kinds);
        $other = new Snapshot(['LBFother' => ['properties' => [], 'fields' => [Rows::field('LBFother', 'x', 1)]]], [], []);
        $kinds = array_column(Differ::compare($other, Rows::snapshot())->findings(), 'item', 'kind');
        $this->assertSame('layout:LBFother', $kinds[Report::ONLY_IN_A]);
    }

    public function testAColumnTheSiteLacksAListNeitherHasAndAnUnknownType(): void
    {
        $file = new Snapshot(['DEM' => ['properties' => [], 'fields' => [
            Rows::field('DEM', 'fname', 1), Rows::field('DEM', 'zz_new', 2, ['list_id' => 'nowhere']), Rows::field('DEM', 'phones', 3, ['data_type' => '55']),
            Rows::field('DEM', 'em_name', 4), Rows::field('DEM', 'em_city', 5),
        ]]], [], []);
        $site = new Snapshot(['DEM' => ['properties' => [], 'fields' => $file->layouts['DEM']['fields']]], [], []);
        $found = array_map(static fn(array $f): string => $f['kind'] . ' ' . $f['item'], Differ::compare($file, $site, self::COLUMNS, [1, 2])->findings());
        $this->assertContains(Report::MISSING_COLUMN . ' patient_data.zz_new', $found);
        $this->assertNotContains(Report::MISSING_COLUMN . ' patient_data.phones', $found, 'telecoms have a table of their own');
        $this->assertNotContains(Report::MISSING_COLUMN . ' patient_data.fname', $found);
        $this->assertContains(Report::MISSING_COLUMN . ' employer_data.city', $found);
        $this->assertNotContains(Report::MISSING_COLUMN . ' employer_data.name', $found);
        $this->assertNotContains(Report::MISSING_COLUMN . ' patient_data.em_name', $found);
        $this->assertContains(Report::MISSING_LIST . ' list:nowhere', $found);
        $this->assertContains(Report::UNKNOWN_TYPE . ' layout:DEM field:phones', $found, 'type 55, which OpenEMR 7.0 lacks');
    }

    public function testAListTheSiteHasIsNotMissing(): void
    {
        // Compared with a site, b's snapshot holds only the file's lists; the site's other lists count too.
        $file = new Snapshot(['LBFx' => ['properties' => [], 'fields' => [Rows::field('LBFx', 'risk', 1, ['list_id' => 'risklevel'])]]], [], []);
        $site = new Snapshot(['LBFx' => ['properties' => [], 'fields' => $file->layouts['LBFx']['fields']]], [], []);
        $this->assertSame([Report::MISSING_LIST], array_column(Differ::compare($file, $site, self::COLUMNS, [2])->findings(), 'kind'));
        $this->assertSame([], Differ::compare($file, $site, self::COLUMNS, [2], ['boolean', 'risklevel'])->findings());
    }

    public function testTableColumnsOneVersionLacksAreReportedButAreNotADifference(): void
    {
        $old = Rows::snapshot(null, null, ['layout_options' => Rows::FIELD_COLUMNS, 'list_options' => ['list_id', 'option_id', 'title', 'seq'],
            'layout_group_properties' => ['grp_form_id', 'grp_group_id', 'grp_title']]);
        $r = Differ::compare($old, Rows::snapshot());
        $this->assertSame([['kind' => Report::COLUMN_ONLY_IN_B, 'item' => 'list_options.last_updated', 'what' => '', 'changes' => []]], $r->findings());
        $this->assertTrue($r->same());
    }

    public function testLastUpdatedIsNotCompared(): void
    {
        $touched = Rows::snapshot(null, [Rows::option('colors', 'red', 1, ['last_updated' => '2026-10-19 08:00:00']), Rows::option('colors', 'blue', 2)]);
        $this->assertTrue(Differ::compare($touched, Rows::snapshot())->same());
    }
}
