<?php

/**
 * The OpenEMR calls Layout Export makes that changed between releases. The shared ones are
 * openhealthcare-common's OpenEmr, one copy for every module (7.0.x to 8.x: settings, session, CSRF,
 * access denied, audit log); this class keeps the names Layout Export's code calls, and the calls only
 * Layout Export makes.
 *
 * @package   OpenHealthcare\LayoutExport
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport\Compat;

use OpenEMR\Common\Acl\AclMain;
use OpenHealthcare\Common\OpenEmr as Common;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class OpenEmr
{
    /** A string setting, such as OE_SITE_DIR. */
    public static function setting(string $name): string
    {
        return Common::setting($name);
    }

    public static function siteDir(): string
    {
        return Common::siteDir();
    }

    public static function webRoot(): string
    {
        return Common::webRoot();
    }

    public static function projectDir(): string
    {
        return Common::projectDir();
    }

    /** The event dispatcher, for subscribing to OpenEMR's events. */
    public static function eventDispatcher(): EventDispatcherInterface
    {
        return Common::eventDispatcher();
    }

    /** A value from the logged-in user's session: pid, authUser, authProvider, authUserID. */
    public static function session(string $key): mixed
    {
        return Common::session($key);
    }

    public static function csrfToken(): string
    {
        return Common::csrfToken();
    }

    /** Ends the request (OpenEMR's own CSRF failure page) unless the posted token is valid. */
    public static function requireCsrf(mixed $token): void
    {
        Common::requireCsrf($token);
    }

    /** Ends the request with OpenEMR's access-denied page, or a plain 403 on releases without one. */
    public static function deny(string $logMessage, string $title): never
    {
        Common::deny($logMessage, $title, 'Layout Export');
    }

    /** The permission check stays here, literal, so the footprint (tools/footprint.php) reads it from the module. */
    public static function isAdministrator(): bool
    {
        return (bool)AclMain::aclCheckCore('admin', 'super');
    }

    public static function version(): string
    {
        return Common::version();
    }

    /**
     * The layout field types this OpenEMR knows (library/layout.inc.php's $datatypes): 7.0 lacks 55
     * (Telecom List) and 56 (Related Person List), which 8.0 added. Null when it can't be read.
     *
     * @return list<int>|null
     */
    public static function fieldTypes(): ?array
    {
        $file = self::projectDir() . '/library/layout.inc.php';
        if (!isset($GLOBALS['datatypes']) && is_file($file)) {
            require_once $file;
        }
        $types = $GLOBALS['datatypes'] ?? null;
        return is_array($types) ? array_map('intval', array_keys($types)) : null;
    }

    /** Writes an event to OpenEMR's audit log as the logged-in user. */
    public static function audit(string $event, string $comment, int $pid = 0): void
    {
        Common::audit($event, $comment, $pid);
    }
}
