# Changelog

## 0.1.8 (2026-10-27)

- **The export's "Also export the lists the chosen layouts use" box and the format choice are easier to hit.** The box sat so close to the format buttons below it that the two 13-pixel targets were less than 24 pixels apart; there is now room between them (WCAG 2.2, 2.5.8 Target Size).

## 0.1.7 (2026-10-26)

- **Core stock fields: see what deleting a field would cost, before you delete it.** OpenEMR's Layout Editor drops a Demographics or History field's table column when the field is deleted and the column is empty and not on its short protected list, and OpenEMR code that reads that column by name fails later, as on a new visit after Referring Provider was deleted (core issue #14742). A new page, **Check core stock fields** at the top of the module's page, compares the site with the stock schema of the OpenEMR it runs (its own `sql/database.sql`) and the protected list in its own `edit_layout.php`, and lists stock columns already gone, with the statement that puts each back with its stock definition; stock fields the editor would drop the column of if deleted now; and stock fields not on the layout. It reads only, and says so when the OpenEMR has no `sql/database.sql` rather than reporting all clear.
- **A guide**, GUIDE.md, task by task, opened by **Help** on each page at the installed version's tag.
- **Install note:** restart the container after adding, upgrading or removing a module copied in by hand (OpenEMR's official 8.0.0 to 8.5.0 images keep class locations, and classes not found, in APCu).

## 0.1.6 (2026-10-26)

- **Pages are announced in your language.** Each page now declares the signed-in user's OpenEMR language and its direction instead of English, so a screen reader reads the translated text in that language. It comes from openhealthcare-common 1.4.0, which this version requires. If another module's older copy of the library loads first on a site that installs by hand, the pages say English, as before, rather than stop.
- **Manual installs:** the README now says that every module's own copy of openhealthcare-common must be the same version, since OpenEMR loads the library once, from the first module that registers it.

## 0.1.5 (2026-10-26)

- **Text and buttons are easier to read on this module's pages.** OpenEMR's blue (#007bff) is 3.97:1 on white, under the 4.5:1 that WCAG 2.1 AA asks of normal text and that HHS's Section 504 rule requires from 2027-05-11. Under OpenEMR's light and manila themes, the module's links and primary buttons now use #0056b3 (7.0:1), and muted and warning text darker shades that stay readable on tinted rows, and a link inside text is underlined; the dark, solar and colour themes keep their own colours, and OpenEMR's own pages are unchanged.

## 0.1.4 (2026-10-25)

- **Fixed: on OpenEMR 7.0.4 and 8.0.0 to 8.0.0.3, enabling this module stopped every OpenEMR page, the login page included (HTTP 500).** Those releases have `OEGlobalsBag` without `getProjectDir()` and the other getters, which came with 8.1.0, and the module called them wherever the class existed. It now reads the values OpenEMR stores there, and openhealthcare-common 1.3.3 does the same for the web root, the kernel and the session (8.0.x's session factory has only `getWrapper()`). Checked on fresh installs of the official 7.0.2, 7.0.4 and 8.0.0 images with every module enabled, and on 8.4.1 and master.

## 0.1.3 (2026-10-23)

- **Shared code:** the OpenEMR compatibility layer (settings, paths, the event dispatcher, CSRF, the refusal page, the audit log) now comes from [openhealthcare-common](https://github.com/anun333/openhealthcare-common) 1.x, which the openhealthcare modules share, instead of a copy in `src/Compat/`. Composer installs it with the module. The module does the same things as before, and its permission checks stay in its own code. Its read of the `version` table moved with it, and FOOTPRINT.md now lists it under the library's path. FOOTPRINT.md reads the library methods the module calls, as part of the module.
- The bootstrap registers the library's namespace before looking for it, since Composer remembers a class it once failed to find. Without the library the module stays off instead of stopping OpenEMR.
- **Accessibility:** the page declares its language (`<html lang>`), and layout and list ids in `code` pass contrast (over 5.6:1; Bootstrap's pink was 3.7:1).
- **README:** run OpenEMR on a PHP with PHP's php2shell fix (2026-10-21: 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later). The official OpenEMR 7.0.2 and 7.0.3 Docker images can't get it.
- **FOOTPRINT.md** now comes from openhealthcare-common's footprint tool (1.1), the one every module shares; `tools/footprint.php` only loads it. The sheet's closing notes, what it can't show, are this module's own (in composer.json).
- **Checked:** unit tests; the nightly matrix on 8.4.1 and master (21 checks each); fresh official 7.0.2, 7.0.3, 8.1.0, 8.3.0 and 8.5.0 images, with every module enabled and with it alone.

## 0.1.2 (2026-10-22)

- **Fixed: a Backup SQL file from MariaDB 12 wasn't read.** MariaDB 12's `mariadb-dump` wraps each table's rows in `SET @@AUTOCOMMIT=0 ... COMMIT`, and the reader refused the `COMMIT` as a statement backup.php never wrote. Transaction control (`COMMIT`, `BEGIN`, `START TRANSACTION`) is now passed over like the dump's `SET` lines; the reader still runs nothing. Found by the nightly check on OpenEMR master, whose stack runs MariaDB 12.3; a test reads that dump.

## 0.1.1 (2026-10-20)

- **Fixed:** three compare labels read "the first file`s OpenEMR", "the file`s, then this site`s" and the like. OpenEMR's `xl()` turns an apostrophe into a backtick, so they're reworded, and a test now fails on any translated string with an apostrophe or quote mark.
- `tools/footprint.php` refuses web requests: Composer installs the package under OpenEMR's web root.

## 0.1.0 (2026-10-20)

First release. It only reads: it changes nothing in OpenEMR.

- **Export** of chosen layouts and lists, as one JSON file or a zip with one file per layout and list, optionally with the lists the layouts use. Every column of the three tables, in a fixed order with no export time, so two exports of an unchanged site are byte-identical.
- **Compare** of a file with the site, or of two files: layouts, lists, form and group properties, fields and options added, removed or changed column by column; fields whose table column the site lacks; lists no side has; field types the site doesn't know; table columns only one version has (listed, not counted as a difference).
- **Files from the old Backup page** (removed in OpenEMR 8.3.0) read as data, never run. Only the statements the page wrote are accepted; anything else refuses the file.
- Where a field is saved follows OpenEMR's own saves: `em_` fields in `employer_data`, list types 52 and 54 to 56 in tables of their own.
- The page and the download need `admin/super`, as OpenEMR's layout and list editors do. Each export and compare is in the audit log.
- Checked on OpenEMR 8.4.1 end to end, and on fresh 7.0.3 and 8.5.0 installs.
