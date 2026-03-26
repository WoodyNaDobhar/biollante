<?php

namespace Biollante\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves scoped access between users and resources.
 *
 * Consuming applications implement this interface to define how users
 * prove authority over scoped entities. Biollante's generated policies
 * call userHasScope() at runtime; generated permission tests call
 * grantScopeForTest() to set up the required database state.
 *
 * The scope type and scope path are discovered at generation time by
 * RoleFieldResolver, which walks the database schema to find FK and
 * morph paths from a resource to its scope entities.
 */
interface ScopeResolver
{
	/**
	 * Does the given user have scoped authority over the specified entity?
	 *
	 * This is called at runtime by generated policies when evaluating
	 * "Related" permission checks. The implementation should determine
	 * whether the user has been granted organizer/manager/team-level
	 * access to the entity identified by $scopeType and $scopeId.
	 *
	 * Hierarchy walking (e.g. "authority over a parent entity grants
	 * access to its children") should be handled here if the consuming
	 * application requires it.
	 *
	 * @param  Model       $user       The authenticated user.
	 * @param  string      $scopeType  The scope entity type (e.g. 'Chapter', 'Practice').
	 * @param  int|string  $scopeId    The scope entity's ID.
	 * @return bool
	 */
	public function userHasScope(Model $user, string $scopeType, int|string $scopeId): bool;

	/**
	 * Set up test database state granting $user scoped authority over the
	 * entity identified by $scopeType and $scopeId.
	 *
	 * Called by generated permission tests to create whatever join records,
	 * pivot entries, or intermediate models the application needs so that
	 * userHasScope() will return true for this user/entity pair.
	 *
	 * The returned array of models will be force-deleted during test
	 * teardown, so include everything that was created.
	 *
	 * @param  Model       $user       The user to grant scope to.
	 * @param  string      $scopeType  The scope entity type.
	 * @param  int|string  $scopeId    The scope entity's ID.
	 * @return Model[]     All models created (for cleanup).
	 */
	public function grantScopeForTest(Model $user, string $scopeType, int|string $scopeId): array;
}