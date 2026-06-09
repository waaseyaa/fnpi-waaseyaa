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
use App\Entity\DocumentNote;
use App\Entity\DriveFile;
use App\Entity\Page;
use App\Entity\Pillar;
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
    new EntityType(
        id: 'identity_pillar',
        label: 'Identity pillar',
        class: Pillar::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'revision' => 'revision_id',
        ],
        revisionable: true,
        revisionDefault: true,
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
];
