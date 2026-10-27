# Layout Export for OpenEMR

Exports OpenEMR's layout-based forms and lists to files you can keep in version control, and compares a file with the site or with another file.

OpenEMR 8.3.0 removed the Backup page (openemr/openemr#13471), and with it the export of layouts and lists that some sites relied on to version-control their forms and undo changes (openemr/openemr#11905). This module does that part again, inside OpenEMR, for OpenEMR 7.0 and later. It also reads the SQL files the old page wrote, as data, without running them, so backups made before the page went can still be compared.

> **Status: 0.1.6, a pre-release.** Tested with synthetic data on OpenEMR 8.4.1, 7.0.3 and 8.5.0 (see [Compatibility](#compatibility)). This version only reads: it changes nothing in OpenEMR. Importing a file is planned for 0.2.0.

What installing it changes in your database (nothing), what it reads, and what it hooks into are listed in [FOOTPRINT.md](FOOTPRINT.md), generated from the source.

## What it does

**Admin › Layout Export** has two parts.

- **Export:** choose layouts (Demographics, History, the LBF encounter forms, the LBT transaction forms and any other) and lists, and download them:
  - as one JSON file, or as a zip with one file per layout and per list, which diffs best in git;
  - with the lists the chosen layouts use, unless you untick that.

  Each file holds every column the site's `layout_options`, `layout_group_properties` and `list_options` tables have, read from the tables themselves, since those differ between versions. It also records which OpenEMR it came from, and the `patient_data`, `employer_data` and `history_data` columns the fields are saved in.

  Rows, columns and keys are in a fixed order and the file has no export time, so two exports of an unchanged site are byte-identical, the zip included. A commit then shows only what changed.
- **Compare:** upload a file and compare it with this site, or upload two and compare them with each other. A file can be a Layout Export JSON file or zip, or an SQL file from the old Backup page. The report lists:
  - layouts and lists only on one side;
  - within a layout, the form's and each group's properties and each field, added, removed or changed, with each changed column's two values;
  - within a list, its own row and each option, the same way;
  - fields whose table column this site lacks;
  - lists the fields use that neither this site nor the file has;
  - field types this OpenEMR doesn't know (8.0 added 55, Telecom List, and 56, Related Person List);
  - table columns one OpenEMR version has and the other doesn't. These are listed but don't count as a difference.

  `list_options.last_updated`, which OpenEMR sets on every edit, isn't compared.

### Files from the old Backup page

The Backup page wrote an SQL file per export: `DELETE` statements for each chosen layout and list, then a `mysqldump` of their rows, and for Demographics and History a statement adding any columns the fields needed. Importing it ran the file through the `mysql` command line, so whatever the file held was executed.

This module reads such a file as data and never runs it. It accepts only what the page wrote:
- `INSERT`s into the three tables;
- the page's `DELETE`s for them;
- the comments, `SET` and `LOCK TABLES` statements `mysqldump` and `mariadb-dump` add;
- the page's column repair, which it recognises and skips, since the compare reports missing columns itself.

Anything else, such as an `INSERT` into another table or a `DROP TABLE`, refuses the whole file. Gzipped files are read too.

### Where a field is saved

The compare's missing-column check follows where OpenEMR itself saves each field, as Demographics and the LBF forms do on save:
- a Demographics field, or a field of any layout whose source is Demographics (D), is a `patient_data` column, unless its id starts `em_`, when it's the `employer_data` column without the prefix;
- a History field, or one whose source is History (H), is a `history_data` column;
- previous names (type 52), addresses (54), telecoms (55) and related persons (56) have tables of their own and need no column;
- other layouts keep their values in rows, not columns.

The old Backup page's repair added a `patient_data` column for every Demographics field, `em_` fields and list types included.

## Who can open what

The page and the download make the same check as OpenEMR's own layout and list editors: `admin/super`. A user without it is refused both. Each export and compare is written to OpenEMR's audit log (event `other`), naming the layouts and lists exported, or the files compared and how many findings there were.

## Not done yet

Planned for 0.2.0, behind a setting that is off by default:
- **Import,** only after a compare, applying the items you tick. Missing columns are added first, then the layouts and lists are replaced in one transaction, with bound statements; nothing from the file is ever run as SQL. Field types the site doesn't know are reported, not written.
- **Snapshots:** before each import, what's about to be replaced is saved in the site, and the page lists the snapshots for restoring.

## Install

```
composer require anun333/oe-module-layout-export
```

Then in OpenEMR, Modules › Manage Modules: Register, Install and Enable **Layout Export**. It has no tables and no install SQL. It brings [openhealthcare-common](https://github.com/anun333/openhealthcare-common), the small library of OpenEMR compatibility code the openhealthcare modules share; Composer installs it alongside.

**Manual installs:** if you copy modules in by hand rather than through Composer, every module's own `vendor/` must hold the same version of openhealthcare-common. OpenEMR loads the library once, from the first module that registers it, so an older copy in one module is what every module gets.

## Compatibility

**PHP security.** PHP's php2shell advisory (2026-10-21; CVE-2026-109618 and CVE-2026-109619) is remote code execution without logging in, and it is being exploited. Run OpenEMR on PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later, whatever the module needs. The official OpenEMR 7.0.2 and 7.0.3 Docker images can't get that fix: they carry PHP 8.2.16 and 8.3.15 on Alpine releases that no longer get updates. Run 7.0 only on a patched PHP, or upgrade OpenEMR. OpenEMR rebuilt its 8.4.1, 8.3.0, 8.2.0 and 8.0.0.3 images with the fix on 2026-10-22 (forum t/27157); a container started before then needs pulling again.

Tested with synthetic data on OpenEMR 8.4.1 (Docker image `openemr/openemr:8.4.1`), with `dev/layout_export_check.py` in the openhealthcare repository. That check:
- installs the module through Module Manager;
- confirms a clinician without `admin/super` is refused the page and the download;
- exports the Referral layout (LBTref) and the Risk Level list, with the lists the layout uses, and gets byte-identical files from two exports, as JSON and as a zip;
- compares with the site, finding no differences, the JSON, the zip, and an SQL file written the way the Backup page wrote it (`mariadb-dump` with its options, after its `DELETE`s), and the JSON with the zip;
- changes one field's title and one option's title on the site, and finds exactly those two;
- adds a made-up field to a Demographics export, and finds that field and the `patient_data` column the site lacks, nothing else;
- refuses an SQL file with a `DROP TABLE`, running nothing in it;
- finds each export and compare in the audit log;
- puts both titles back, and checksums the three tables against before.

On fresh installs of the official 7.0.3 and 8.5.0 images (`dev/module_compat_check.sh`), it installs through Module Manager, the page loads, two exports of LBTref are byte-identical, and the export compared with the site finds no differences.

The three tables are the same from 7.0.3 to 8.5. Files are read by column name, so a file from an older version, which lacks a few columns, compares too: the columns only one side has are listed separately.

It needs PHP 8.2 or later and PHP's zip and zlib extensions. OpenEMR 6.x and older aren't supported: their images run PHP 8.0.

## Development

```
composer install
vendor/bin/phpunit
php tools/footprint.php          # regenerate FOOTPRINT.md after changing the module
php tools/footprint.php --check  # what CI runs
```

The unit tests need no database or OpenEMR install. They include an SQL file `mariadb-dump` wrote with the Backup page's options (`tests/fixtures/backup-php-LBTref.sql`). CI runs them, and the footprint check, on PHP 8.2 to 8.5.

## Other modules for OpenEMR

From the same project. Each installs with `composer require anun333/<module>` and is then enabled in Modules › Manage Modules.

| Module | What it does |
|---|---|
| [Clinic Forms](https://github.com/anun333/oe-module-clinic-forms) | Printable clinic paperwork: consents, privacy notices, referral letters and validated screening questionnaires, with the clinic's own details, in English and Spanish |
| [UDS Report](https://github.com/anun333/oe-module-uds-report) | HRSA's Uniform Data System report for health centers: the patient, visit and diagnosis tables, eCQM readiness, a Table 9D worksheet and grant evidence |
| [Staff Worklist](https://github.com/anun333/oe-module-staff-worklist) | One queue of outstanding staff work: unsigned results, orders with no result, referrals with no reply, screening and claims follow-up |
| [Sliding Fee Discount](https://github.com/anun333/oe-module-sliding-fee) | HRSA's sliding fee discount program: the schedule checked against Chapter 9, income assessments, each visit's discount and the evaluation figures |
| [Care Management](https://github.com/anun333/oe-module-care-management) | CCM, PCM and BHI enrollment with CMS's consent elements, monthly time logging, and the codes each month meets |
| [Migration](https://github.com/anun333/oe-module-migration) | Records from another EHR, read from the FHIR export certified EHRs offer, checked before import and safe to rerun |

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
