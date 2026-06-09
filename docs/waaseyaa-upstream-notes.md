# Waaseyaa upstream notes

Running log of framework quirks, gaps, and changes worth remembering when building
FNPI on Waaseyaa. Newest first. These are notes about the *upstream* framework
(`waaseyaa/framework`), kept here so app work does not re-discover them.

## 2026-06-08 — Published-revision pointer (added upstream, alpha.195)

Built for the Anokii Pages capability (public site driven from `page` entities).

- **Gap found:** the revision layer had a single base-table pointer
  (`revision_id`) that always means *current/latest*. There was no separate
  "published" pointer, so the live view and an in-progress draft could not
  differ. The `workflows` package's `workflow_state` is a flag on the current
  revision, not a second revision pointer.
- **Two parallel revision subsystems exist** — know which one you are on:
  - **`EntityRepository` + `SqlSchemaHandler` + `Driver/RevisionableStorageDriver`**
    — table `<entity>_revision` (single underscore), base pointer column
    `revision_id`, snapshot id column `revision_id`. This is what FNPI content
    entities use (`page`, `identity_pillar`, `document` — all registered
    `revisionable: true` and accessed via `EntityRepositoryInterface`).
  - **`RevisionableSqlBlobStorage` / `RevisionableSqlColumnStorage` + `RevisionRowHydrator` + `Schema/RevisionTableBuilder`**
    — table `<entity>__revision` (double underscore), pointer/snapshot column
    `vid`. This is the newer two-axis (revisionable × translatable) path. FNPI
    does **not** use it.
- **Change made (additive, backward-compatible):** added a nullable
  `published_revision_id` column to the revisionable base table in
  `SqlSchemaHandler::buildTableSpec()`, plus `loadPublishedRevision()` /
  `setPublishedRevision()` on `EntityRepository` and `EntityRepositoryInterface`
  (the vid-based subsystem was intentionally left untouched). The column
  defaults to NULL, so every pre-existing revisionable row is unaffected, and
  `loadPublishedRevision()` returns null on base tables that predate the column.
  The base-table-only pointer columns are skipped by `SqlSchemaHandler::seedRevisions()`.
- **How to use it for `page`:** `find()` / latest revision is the working draft;
  `loadPublishedRevision($id)` is what the public route renders; `setPublishedRevision($id, $rev)`
  is the deliberate "go live" step; publishing an older revision is rollback.

## 2026-06-08 — VERSION file was frozen at alpha.4 (fixed upstream, alpha.195)

The framework's `VERSION` file read `0.1.0-alpha.4` at every tag because no
release step ever wrote it; the real version lives in git tags. A cold clone of
`waaseyaa/framework` therefore misreported its version (use `git describe --tags`,
not the file). Fixed in `release-cut.yml` (and the `scripts/release.sh` fallback):
the cut now stamps `VERSION` to match the tag, starting at alpha.195.
