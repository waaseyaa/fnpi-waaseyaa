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
use Waaseyaa\Entity\EntityType;

return [
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
