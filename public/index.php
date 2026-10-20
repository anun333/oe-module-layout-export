<?php

/**
 * Layout Export: choose layouts and lists to export, or compare a file (a Layout Export JSON or
 * zip, or an SQL file from the old backup.php page) with this site or with another file. This
 * version changes nothing in OpenEMR.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Core\Header;
use OpenHealthcare\LayoutExport\Bootstrap;
use OpenHealthcare\LayoutExport\Compare\Differ;
use OpenHealthcare\LayoutExport\Compare\Report;
use OpenHealthcare\LayoutExport\Compat\OpenEmr;
use OpenHealthcare\LayoutExport\Snapshot\FileError;
use OpenHealthcare\LayoutExport\Snapshot\FileReader;
use OpenHealthcare\LayoutExport\Snapshot\SiteReader;

// The check OpenEMR's own layout and list editors make.
if (!OpenEmr::isAdministrator()) {
    OpenEmr::deny('ACL check failed for admin/super: Layout Export', xl('Layout Export'));
}
$site = new SiteReader();
$layouts = $site->layouts();
$lists = $site->lists();
$report = null;
$compared = '';
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'compare') {
    OpenEmr::requireCsrf($_POST['csrf_token_form'] ?? '');
    $upload = static function (string $name) use (&$errors): ?array {
        $f = $_FILES[$name] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) {
            $errors[] = xl('The file could not be uploaded.');
            return null;
        }
        try {
            [$snapshot, $kind] = FileReader::read((string)$f['tmp_name']);
            return ['snapshot' => $snapshot, 'kind' => $kind, 'name' => (string)$f['name']];
        } catch (FileError $e) {
            $errors[] = (string)$f['name'] . ': ' . $e->getMessage();
            return null;
        }
    };
    $a = $upload('file_a');
    $b = $upload('file_b');
    if ($a === null && $errors === []) {
        $errors[] = xl('Choose a file to compare.');
    }
    if ($a !== null && $errors === []) {
        if ($b !== null) {
            $report = Differ::compare($a['snapshot'], $b['snapshot']);
            $compared = sprintf(xl('%1$s (%2$s) compared with %3$s (%4$s)'), $a['name'], $a['kind'], $b['name'], $b['kind']);
        } else {
            $here = $site->snapshot(array_map('strval', array_keys($a['snapshot']->layouts)), array_map('strval', array_keys($a['snapshot']->lists)), false, OpenEmr::version());
            $columns = [];
            foreach (['employer_data', 'history_data', 'patient_data'] as $table) {
                $columns[$table] = $site->columns($table);
            }
            $report = Differ::compare($a['snapshot'], $here, $columns, OpenEmr::fieldTypes(), array_map('strval', array_keys($lists)));
            $compared = sprintf(xl('%1$s (%2$s) compared with this site'), $a['name'], $a['kind']);
        }
        OpenEmr::audit('other', 'Layout Export: compared ' . $a['name'] . ($b !== null ? ' with ' . $b['name'] : ' with this site')
            . ', ' . count($report->findings()) . ' findings');
    }
}
$withB = isset($b) && $b !== null;
$kinds = [
    Report::ONLY_IN_A => $withB ? xl('Only in the first file') : xl('In the file, not on this site'),
    Report::ONLY_IN_B => $withB ? xl('Only in the second file') : xl('On this site, not in the file'),
    Report::ROW_ONLY_IN_A => $withB ? xl('Only in the first file') : xl('In the file, not on this site'),
    Report::ROW_ONLY_IN_B => $withB ? xl('Only in the second file') : xl('On this site, not in the file'),
    Report::CHANGED => xl('Different'),
    Report::MISSING_COLUMN => xl('A column this site lacks'),
    Report::MISSING_LIST => xl('A list the fields use that neither has'),
    Report::COLUMN_ONLY_IN_A => $withB ? xl('A table column only the first file\'s OpenEMR has') : xl('A table column only the file\'s OpenEMR has'),
    Report::COLUMN_ONLY_IN_B => $withB ? xl('A table column only the second file\'s OpenEMR has') : xl('A table column only this OpenEMR has'),
    Report::UNKNOWN_TYPE => xl('A field type this OpenEMR does not know'),
];
$token = OpenEmr::csrfToken();
$base = OpenEmr::webRoot() . Bootstrap::MODULE_INSTALLATION_PATH . '/public/';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Layout Export'); ?></title>
    <?php Header::setupHeader(); ?>
</head>
<body class="body_top">
<div class="container-fluid py-3">
    <h1 class="h4"><?php echo xlt('Layout Export'); ?></h1>
    <p class="small text-muted"><?php echo xlt('Export layout forms and lists to a file you can keep in version control, and compare a file with this site or with another file. It reads the SQL files the old Backup page wrote too, as data, without running them. This version changes nothing in OpenEMR.'); ?></p>
    <?php foreach ($errors as $e) { ?><div class="alert alert-danger py-2"><?php echo text($e); ?></div><?php } ?>

    <?php if ($report !== null) { ?>
        <h2 class="h5" id="result"><?php echo xlt('Compare'); ?></h2>
        <p class="small"><?php echo text($compared); ?></p>
        <?php if ($report->same()) { ?>
            <div class="alert alert-success py-2" id="same"><?php echo xlt('No differences in the layouts and lists.'); ?></div>
        <?php } ?>
        <?php if ($report->findings() !== []) { ?>
        <table class="table table-sm" id="findings">
            <thead><tr><th><?php echo xlt('What'); ?></th><th><?php echo xlt('Where'); ?></th><th><?php echo xlt('Detail'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($report->findings() as $f) { ?>
                <tr data-kind="<?php echo attr($f['kind']); ?>" data-item="<?php echo attr($f['item']); ?>">
                    <td><?php echo text($kinds[$f['kind']] ?? $f['kind']); ?></td>
                    <td><code><?php echo text($f['item']); ?></code></td>
                    <td><?php echo text($f['what']); ?>
                        <?php foreach ($f['changes'] as $c) { ?>
                            <div class="small" data-column="<?php echo attr($c['column']); ?>"><code><?php echo text($c['column']); ?></code>:
                                <?php echo text($c['a'] ?? 'NULL'); ?> → <?php echo text($c['b'] ?? 'NULL'); ?></div>
                        <?php } ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <p class="small text-muted"><?php echo $withB ? xlt('For a changed value: the first file\'s, then the second\'s.') : xlt('For a changed value: the file\'s, then this site\'s.'); ?></p>
        <?php } ?>
    <?php } ?>

    <h2 class="h5 mt-3"><?php echo xlt('Export'); ?></h2>
    <form method="post" action="<?php echo attr($base . 'export.php'); ?>" id="export">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-1"><strong><?php echo xlt('Layouts'); ?></strong></p>
                <div class="border rounded p-2" style="max-height: 20rem; overflow-y: auto">
                <?php foreach ($layouts as $id => $title) { ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="layouts[]" value="<?php echo attr($id); ?>" id="l-<?php echo attr($id); ?>">
                        <label class="form-check-label small" for="l-<?php echo attr($id); ?>"><?php echo text($title); ?> <code><?php echo text($id); ?></code></label></div>
                <?php } ?>
                </div>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong><?php echo xlt('Lists'); ?></strong></p>
                <div class="border rounded p-2" style="max-height: 20rem; overflow-y: auto">
                <?php foreach ($lists as $id => $title) { ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="lists[]" value="<?php echo attr($id); ?>" id="t-<?php echo attr($id); ?>">
                        <label class="form-check-label small" for="t-<?php echo attr($id); ?>"><?php echo text($title); ?> <code><?php echo text($id); ?></code></label></div>
                <?php } ?>
                </div>
            </div>
        </div>
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="with_used_lists" value="1" id="with_used_lists" checked>
            <label class="form-check-label small" for="with_used_lists"><?php echo xlt('Also export the lists the chosen layouts use'); ?></label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="format" value="json" id="f-json" checked>
            <label class="form-check-label small" for="f-json"><?php echo xlt('One JSON file'); ?></label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="format" value="zip" id="f-zip">
            <label class="form-check-label small" for="f-zip"><?php echo xlt('A zip with one file per layout and list (diffs best in git)'); ?></label></div>
        <div><button class="btn btn-sm btn-primary mt-2" type="submit"><?php echo xlt('Export'); ?></button></div>
    </form>

    <h2 class="h5 mt-4"><?php echo xlt('Compare'); ?></h2>
    <form method="post" enctype="multipart/form-data" id="compare">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>">
        <input type="hidden" name="action" value="compare">
        <div class="form-group"><label for="file_a" class="small"><?php echo xlt('A Layout Export file (JSON or zip), or an SQL file from the old Backup page'); ?></label>
            <input type="file" class="form-control-file" name="file_a" id="file_a"></div>
        <div class="form-group"><label for="file_b" class="small"><?php echo xlt('Optionally a second file, to compare the two; leave it empty to compare with this site'); ?></label>
            <input type="file" class="form-control-file" name="file_b" id="file_b"></div>
        <button class="btn btn-sm btn-primary" type="submit"><?php echo xlt('Compare'); ?></button>
    </form>
</div>
</body>
</html>
