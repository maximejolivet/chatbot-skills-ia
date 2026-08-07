<?php

namespace App\Entity;

/**
 * A resource attributable to the operator account that created it, used by
 * OwnershipVoter/OwnershipCollectionExtension to scope non-admin API access
 * to a user's own rows.
 */
interface OwnedResourceInterface
{
    public function getOwnerUser(): ?User;

    /**
     * The Doctrine-mapped property name backing getOwnerUser(), for
     * OwnershipCollectionExtension to build a DQL WHERE clause with (DQL
     * needs the actual field name, not the interface method).
     */
    public static function getOwnerFieldName(): string;
}
