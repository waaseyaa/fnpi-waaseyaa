<?php

declare(strict_types=1);

/**
 * Application-specific entity types.
 *
 * The generic workspace entities (page, document, document_note, drive_asset,
 * identity_pillar, contact_submission) are now registered by the anokii
 * distribution (Anokii\Provider\WorkspaceServiceProvider). Only FNPI's bespoke
 * Venture domain types remain here.
 */

use App\Entity\GatingFact;
use App\Entity\VentureItem;
use App\Entity\VentureLane;
use App\Entity\VentureSnapshot;
use App\Entity\VentureThread;
use Waaseyaa\Entity\EntityType;

return [
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

    // Venture Numbers (staff-only): revisionable so every numeric or status edit
    // records a revision (the rollback net); VentureAccessPolicy gates view on the
    // `view ventures` permission. Registered via fromClass() so #[Field]
    // declarations drive save-time validation.
    EntityType::fromClass(VentureLane::class, revisionable: true, revisionDefault: true),
    EntityType::fromClass(GatingFact::class, revisionable: true, revisionDefault: true),
    EntityType::fromClass(VentureSnapshot::class, revisionable: true, revisionDefault: true),
];
