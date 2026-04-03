<?php

namespace Biollante\Traits;

/**
 * Provides a jsPermissions() method that serializes the user's
 * Spatie roles and permissions into a JSON string suitable for
 * frontend consumption.
 *
 * Replaces the `ahmedsaoud31/laravel-permission-to-vuejs` package.
 *
 * Usage: add `use \Biollante\Traits\HasJsPermissions;` to your User model.
 *
 * The returned JSON has the shape:
 * {
 *     "roles": ["Admin", "User"],
 *     "permissions": ["display events", "store events"]
 * }
 */
trait HasJsPermissions
{
	/**
	 * Get JSON-encoded roles and permissions for frontend use.
	 *
	 * @return string JSON string
	 */
	public function jsPermissions(): string
	{
		return json_encode([
			'roles'       => $this->getRoleNames()->toArray(),
			'permissions' => $this->getAllPermissions()->pluck('name')->toArray(),
		]);
	}
}