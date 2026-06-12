<?php

declare(strict_types=1);

/**
 * Application-specific entity types.
 *
 * The Documents tool is the first entity-native Anokii tool: it uses registered
 * entities and the framework revision system instead of the raw-table shell
 * idiom. These types are materialized on deploy by `db:init --sync-schema`
 * (the alpha.191 schema-sync capability), which creates `document`,
 * `document__revision`, and `document_note` on the sovereign volume.
 */

use App\Entity\Document;
use App\Entity\ContactSubmission;
use App\Entity\VentureItem;
use App\Entity\VentureThread;
use App\Entity\DocumentNote;
use App\Entity\DriveFile;
use App\Entity\GatingFact;
use App\Entity\Page;
use App\Entity\Pillar;
use App\Entity\VentureLane;
use App\Entity\VentureSnapshot;
use Waaseyaa\Entity\EntityType;

return [
    // Drive file. Revisionable, entity-native rebuild of the raw `drive_file`
    // table. The id is `drive_asset` (the legacy table is `drive_file`, so a
    // distinct id avoids a schema-sync collision); bytes stay in the media
    // layer, only metadata + storage_uri ride the revisioned _data blob.
    // Tables drive_asset + drive_asset_revision land via db:init --sync-schema.
    new EntityType(
        id: 'drive_asset',
        label: 'Drive file',
        class: DriveFile::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'name',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
    ),

    // Identity Workspace pillar. Revisionable: each edit (status / notes) is one
    // revision, so identity content carries full history. The entity-native
    // rebuild of the raw `pillar` prototype; tables identity_pillar +
    // identity_pillar_revision are materialized by db:init --sync-schema.
    //
    // Translatable (two-axis): English is the default-language base row; each
    // peer language (Anishinaabemowin, `oj`) is a true `(id, langcode)` row with
    // its OWN independent revision history. The translatable fields are `title`
    // and `body` (the pillar name + its canonical "Now" statement — the moat);
    // status, notes, decision and the rest are non-translatable workspace state
    // that stays on the English row. Edits flow through
    // EntityRepository::saveTranslation() (peer base row + per-language revision,
    // atomic — framework alpha.198). Adds the langcode / default_langcode
    // columns and widens the primary key to (id, langcode); db:init
    // --sync-schema materializes identity_pillar__translation__revision.
    new EntityType(
        id: 'identity_pillar',
        label: 'Identity pillar',
        class: Pillar::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'revision' => 'revision_id',
            'langcode' => 'langcode',
            'default_langcode' => 'default_langcode',
        ],
        revisionable: true,
        revisionDefault: true,
        translatable: true,
    ),

    // Revisionable: each version of a document is one revision. revisionDefault
    // true so every save records a new version; listRevisions() is the history.
    new EntityType(
        id: 'document',
        label: 'Document',
        class: Document::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
    ),

    // Public marketing page (Anokii Pages). Revisionable: the route renders the
    // published revision while edits create newer draft revisions, and Publish
    // moves the framework's published-revision pointer (alpha.195). Tables page +
    // page_revision land via db:init --sync-schema; the base table carries the
    // published_revision_id pointer column from the same release.
    new EntityType(
        id: 'page',
        label: 'Page',
        class: Page::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
    ),

    // Inbound public contact-form submission; not revisionable (immutable
    // record, only the read flag mutates). Created only by the public
    // ContactSubmitController; staff read it in the Anokii Inbox. Table lands
    // via db:init --sync-schema like the rest.
    new EntityType(
        id: 'contact_submission',
        label: 'Contact submission',
        class: ContactSubmission::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'email',
        ],
    ),

    // Venture tracker (staff-only working board, MCP-maintained): two flat,
    // non-revisionable types. A thread is a card; items are its status lines.
    new EntityType(
        id: 'venture_thread',
        label: 'Venture thread',
        class: VentureThread::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
        ],
    ),
    new EntityType(
        id: 'venture_item',
        label: 'Venture item',
        class: VentureItem::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'body',
        ],
    ),

    // Flat discussion thread; not revisionable.
    new EntityType(
        id: 'document_note',
        label: 'Document note',
        class: DocumentNote::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'author_label',
        ],
    ),

    // Venture Numbers (staff-only). All three types are revisionable with
    // revisionDefault so every numeric or status edit records a revision (the
    // rollback net); VentureAccessPolicy gates view on the `view ventures`
    // permission (the section is invisible to the public AND to authenticated
    // accounts without it). Tables land via db:init --sync-schema.
    new EntityType(
        id: 'venture_lane',
        label: 'Venture lane',
        class: VentureLane::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
    ),

    new EntityType(
        id: 'gating_fact',
        label: 'Gating fact',
        class: GatingFact::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'label',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
    ),

    new EntityType(
        id: 'venture_snapshot',
        label: 'Venture snapshot',
        class: VentureSnapshot::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'model_version',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
    ),
];
