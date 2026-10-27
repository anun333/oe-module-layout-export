# Layout Export: guide

For the administrator who looks after OpenEMR's layout forms and lists, task by task. What the module is for and how to install it are in the [README](README.md).

The module only reads: it never changes a layout, a list or a table. Its pages need OpenEMR's administrator permission, the one OpenEMR's own Layout Editor needs. It has been tested only on made-up data.

## Keep your layouts and lists in files

*Where:* **Layout Export** in OpenEMR's menu, the **Export** section.

1. Under **Layouts** and **Lists**, tick what to export. **Also export the lists the chosen layouts use** is ticked to start with, so a layout's lists travel with it.
2. Choose **One JSON file**, or **A zip with one file per layout and list (diffs best in git)** to keep them in version control.
3. Choose **Export**. The file downloads.

## See what changed

*Where:* the **Compare** section of the same page.

1. Choose a file: a Layout Export file (JSON or zip), or an SQL file the old Backup page wrote. That page's files are read as data; nothing in them is run.
2. Leave the second file empty to compare the file with this site, or choose a second file to compare the two.
3. Choose **Compare**. Each difference is listed with what it is, where, and the values on each side: a field or list item only on one side, a value that differs, a column the site's tables lack, or a field type this OpenEMR doesn't know.

## Before you delete a field: check core's stock fields

*Where:* **Check core stock fields**, at the top of the page. It opens **Core stock fields**.

OpenEMR's Layout Editor drops a field's table column when you delete a Demographics or History field whose column is empty and not on the short list OpenEMR protects (core issue [#14742](https://github.com/openemr/openemr/issues/14742)). OpenEMR's own code reads many of those columns by name, so a page fails much later with an unknown column error: a new visit, after Referring Provider was deleted, is the case that issue reports. This page compares your Demographics and History layouts with the stock schema of the OpenEMR you run, read from its own `sql/database.sql`, and lists:

- **Stock columns that are gone**: deleted with their field. OpenEMR code that reads them fails. Each row shows, under **To put it back**, the statement that adds the column back with its stock definition. Run it in the database yourself (or ask whoever runs the server); the page runs nothing. Re-adding the field in the Layout Editor also restores the column, but as a text column rather than its stock type.
- **Fields the Layout Editor would drop the column of, if deleted**: empty and unprotected. Don't delete these; hide the field in the Layout Editor instead (its UOR setting to Unused), which keeps the column.
- **Stock fields not on your layout**: deleted from the layout, or never added by an upgrade. The column is still there, so nothing is lost; staff can't see or edit the field until it's added back.

When every stock field is on its layout and every stock column is there, the page says so. If the OpenEMR you run has no `sql/database.sql`, nothing is checked, and the page says that instead of saying all is well.

## Help

**Help** on each page opens this guide for the version you have installed.
