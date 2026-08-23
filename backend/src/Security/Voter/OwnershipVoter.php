<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\OwnedResourceInterface;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Item-level ownership check for OwnedResourceInterface resources (used via
 * `security: "is_granted('OWNER', object)"` on API Platform operations).
 * ROLE_ADMIN bypasses ownership entirely (attribution, not a restriction for
 * admins). A null owner (rows created before multi-user auth existed) is
 * admin-only -- it can't be attributed to whichever ROLE_USER happens to ask.
 *
 * Collection-level filtering (GetCollection) is handled separately by
 * OwnershipCollectionExtension -- a Voter only gates single-item access.
 */
final class OwnershipVoter extends Voter
{
    private const string ATTRIBUTE = 'OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && $subject instanceof OwnedResourceInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var OwnedResourceInterface $subject */
        $owner = $subject->getOwnerUser();

        return null !== $owner && $owner->getId() === $user->getId();
    }
}
