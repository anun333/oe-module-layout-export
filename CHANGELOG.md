# Changelog

## 0.1.3 (unreleased)

- **Shared code:** the OpenEMR compatibility layer (settings, paths, the event dispatcher, CSRF, the refusal page, the audit log) now comes from [openhealthcare-common](https://github.com/anun333/openhealthcare-common) 1.x, which the openhealthcare modules share, instead of a copy in `src/Compat/`. Composer installs it with the module. The module does the same things as before, and its permission checks stay in its own code. Its read of the `version` table moved with it, and FOOTPRINT.md now lists it under the library's path. FOOTPRINT.md reads the library methods the module calls, as part of the module.
- The bootstrap registers the library's namespace before looking for it, since Composer remembers a class it once failed to find. Without the library the module stays off instead of stopping OpenEMR.

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
