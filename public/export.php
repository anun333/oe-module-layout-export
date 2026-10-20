<?php

/**
 * Layout Export: downloads the chosen layouts and lists as one JSON file or a zip. It only reads.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenHealthcare\LayoutExport\Compat\OpenEmr;
use OpenHealthcare\LayoutExport\Snapshot\JsonCodec;
use OpenHealthcare\LayoutExport\Snapshot\SiteReader;

if (!OpenEmr::isAdministrator()) {
    OpenEmr::deny('ACL check failed for admin/super: Layout Export', xl('Layout Export'));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
OpenEmr::requireCsrf($_POST['csrf_token_form'] ?? '');
$site = new SiteReader();
$known = ['layouts' => $site->layouts(), 'lists' => $site->lists()];
$pick = static fn(string $name): array => array_values(array_filter(
    array_map('strval', is_array($_POST[$name] ?? null) ? $_POST[$name] : []),
    static fn(string $id): bool => isset($known[$name][$id]),
));
$layouts = $pick('layouts');
$lists = $pick('lists');
if ($layouts === [] && $lists === []) {
    http_response_code(400);
    echo text(xl('Choose at least one layout or list.'));
    exit;
}
$snapshot = $site->snapshot($layouts, $lists, !empty($_POST['with_used_lists']), OpenEmr::version());
OpenEmr::audit('other', 'Layout Export: exported layouts ' . implode(',', array_keys($snapshot->layouts)) . '; lists ' . implode(',', array_keys($snapshot->lists)));
$stem = 'openemr-layouts-' . date('Y-m-d');
header('Cache-Control: no-store');
if (($_POST['format'] ?? '') === 'zip') {
    $path = tempnam(sys_get_temp_dir(), 'lex');
    if ($path === false) {
        http_response_code(500);
        exit;
    }
    JsonCodec::encodeZip($snapshot, $path);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $stem . '.zip"');
    readfile($path);
    unlink($path);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $stem . '.json"');
echo JsonCodec::encode($snapshot);
