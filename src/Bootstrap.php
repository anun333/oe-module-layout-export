<?php

/**
 * Hooks the module into OpenEMR: an Admin menu entry for administrators, beside the layout editor.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\LayoutExport;

use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Bootstrap
{
    public const MODULE_INSTALLATION_PATH = '/interface/modules/custom_modules/oe-module-layout-export';

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, $this->addMenuItem(...));
    }

    public function addMenuItem(MenuEvent $event): MenuEvent
    {
        $item = new \stdClass();
        $item->requirement = 0;
        $item->target = 'lex';
        $item->menu_id = 'lex0';
        $item->label = xlt('Layout Export');
        $item->url = self::MODULE_INSTALLATION_PATH . '/public/index.php';
        $item->children = [];
        // The check OpenEMR's own layout and list editors make.
        $item->acl_req = ['admin', 'super'];
        $item->global_req = [];

        $menu = $event->getMenu();
        foreach ($menu as $entry) {
            if (($entry->menu_id ?? '') === 'admimg' && is_array($entry->children ?? null)) {
                $entry->children[] = $item;
                $event->setMenu($menu);
                return $event;
            }
        }
        $menu[] = $item;
        $event->setMenu($menu);
        return $event;
    }
}
