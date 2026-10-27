<?php

/**
 * Layout Export: core's stock fields. Compares this site's table-backed layouts (Demographics, History) and their
 * table columns with the stock schema of the OpenEMR it runs (sql/database.sql), so an administrator sees what core
 * issue #14742 can cost before deleting a field: OpenEMR's Layout Editor drops an empty, unreserved column with its
 * field, and core code reading that column by name then fails. Reads only; changes nothing.
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
use OpenHealthcare\LayoutExport\Compat\OpenEmr;
use OpenHealthcare\LayoutExport\Guide;
use OpenHealthcare\LayoutExport\Snapshot\SiteReader;
use OpenHealthcare\LayoutExport\Stock\Reserved;
use OpenHealthcare\LayoutExport\Stock\StockCheck;
use OpenHealthcare\LayoutExport\Stock\StockSchema;

// The check OpenEMR's own layout editor makes.
if (!OpenEmr::isAdministrator()) {
    OpenEmr::deny('ACL check failed for admin/super: Layout Export stock fields', xl('Layout Export'));
}
$schemaFile = OpenEmr::projectDir() . '/sql/database.sql';
$editorFile = OpenEmr::projectDir() . '/interface/super/edit_layout.php';
$findings = null;
$reserved = null;
if (is_readable($schemaFile)) {
    $stock = StockSchema::fromSql((string)file_get_contents($schemaFile));
    $reserved = is_readable($editorFile) ? Reserved::fromSource((string)file_get_contents($editorFile)) : null;
    $site = new SiteReader();
    $siteFields = $site->layoutFields(array_keys($stock->fields));
    $siteColumns = [];
    $empty = [];
    foreach (array_unique(array_map(StockSchema::tableFor(...), array_keys($stock->fields))) as $table) {
        $siteColumns[$table] = $site->tableColumns($table);
        $empty[$table] = $site->emptyColumns($table, $siteColumns[$table]);
    }
    $findings = StockCheck::findings($stock, $siteFields, $siteColumns, $empty, $reserved);
    OpenEmr::audit('other', 'Layout Export: checked core stock fields, ' . count($findings) . ' findings');
}
$titles = [
    StockCheck::DROPPED => xl('Stock columns that are gone'),
    StockCheck::WOULD_DROP => xl('Fields the Layout Editor would drop the column of, if deleted'),
    StockCheck::NOT_ON_LAYOUT => xl('Stock fields not on your layout'),
];
$notes = [
    StockCheck::DROPPED => xl('OpenEMR code reads these columns by name, so the pages that do fail with an unknown column error. Each can be put back with its stock definition; the statement is shown, for an administrator to run in the database. This page runs nothing.'),
    StockCheck::WOULD_DROP => xl('These columns are empty and not on the list OpenEMR protects, so deleting the field in the Layout Editor drops the column too. Hide the field instead of deleting it.'),
    StockCheck::NOT_ON_LAYOUT => xl('Deleted from the layout, or never added by an upgrade. The column is still there, so nothing is lost; staff just cannot see or edit it.'),
];
$base = OpenEmr::webRoot() . Bootstrap::MODULE_INSTALLATION_PATH . '/public/';
?>
<!DOCTYPE html>
<?php // The page is in the signed-in user's OpenEMR language, so translated text is announced in it. ?>
<html lang="<?php echo attr(OpenEmr::pageLang()); ?>" dir="<?php echo attr(OpenEmr::pageDir()); ?>">
<head>
    <title><?php echo xlt('Core stock fields'); ?></title>
    <?php Header::setupHeader(); ?>
    <?php echo \OpenHealthcare\LayoutExport\Contrast::style(); ?>
    <style>/* Bootstrap's code colour is 3.7:1 on these backgrounds; this one is over 5.6:1 (WCAG 1.4.3). */ code { color: #b5175b; }</style>
</head>
<body class="body_top">
<div class="container-fluid py-3">
    <h1 class="h4"><?php echo xlt('Core stock fields'); ?></h1>
    <p class="small">
        <a href="<?php echo attr($base . 'index.php'); ?>"><?php echo xlt('Back to Layout Export'); ?></a> ·
        <a href="<?php echo attr(Guide::url()); ?>" target="_blank" rel="noopener"><?php echo xlt('Help'); ?></a>
    </p>
    <p class="small text-muted"><?php echo xlt('The Demographics and History layouts compared with the stock schema of this OpenEMR (its sql/database.sql). Deleting a field in the Layout Editor drops its table column when the column is empty, and OpenEMR code that reads a stock column then fails. This page reads only.'); ?></p>
    <?php if ($findings === null) { ?>
        <div class="alert alert-warning py-2" id="no-schema"><?php echo xlt('This OpenEMR has no sql/database.sql to read the stock schema from, so nothing was checked.'); ?></div>
    <?php } else { ?>
        <?php if ($reserved === null) { ?>
            <div class="alert alert-warning py-2" id="no-reserved"><?php echo xlt('The list of columns the Layout Editor protects could not be read from this OpenEMR, so fields it would drop are not listed.'); ?></div>
        <?php } ?>
        <?php if ($findings === []) { ?>
            <div class="alert alert-success py-2" id="clean"><?php echo xlt('Every stock field is on its layout, and every stock column is there.'); ?></div>
        <?php } ?>
        <?php foreach ($titles as $kind => $title) {
            $rows = array_values(array_filter($findings, static fn(array $f): bool => $f['kind'] === $kind));
            if ($rows === []) {
                continue;
            } ?>
            <h2 class="h5 mt-3" id="<?php echo attr($kind); ?>"><?php echo text($title); ?> (<?php echo text((string)count($rows)); ?>)</h2>
            <p class="small"><?php echo text($notes[$kind]); ?></p>
            <table class="table table-sm" data-kind="<?php echo attr($kind); ?>">
                <thead><tr><th scope="col"><?php echo xlt('Layout'); ?></th><th scope="col"><?php echo xlt('Field'); ?></th>
                    <?php if ($kind === StockCheck::DROPPED) { ?><th scope="col"><?php echo xlt('To put it back'); ?></th><?php } ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $f) { ?>
                    <tr data-field="<?php echo attr($f['field']); ?>">
                        <td><code><?php echo text($f['layout']); ?></code></td>
                        <td><code><?php echo text($f['table'] . '.' . $f['field']); ?></code></td>
                        <?php if ($kind === StockCheck::DROPPED) { ?><td><code><?php echo text(StockCheck::repair($f['table'], $f['definition'])); ?></code></td><?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    <?php } ?>
</div>
</body>
</html>
