<?php

namespace Biollante\Traits;

use Illuminate\Http\Request;

/**
 * Shares Spatie permission data through Inertia's shared props.
 *
 * Use this trait in your application's HandleInertiaRequests middleware
 * to automatically share the authenticated user's roles and permissions
 * with every Inertia page response.
 *
 * Frontend components can then access permissions via:
 *   usePage().props.auth.jsPermissions
 *
 * Or use the published `usePermissions` Vue composable for convenience.
 */
trait SharesPermissions
{
	/**
	 * Get the permission-related shared data.
	 *
	 * Merge the return value of this method into your share() array:
	 *
	 *   return array_merge(parent::share($request), $this->sharedPermissions($request));
	 *
	 * @param  Request $request
	 * @return array<string, mixed>
	 */
	protected function sharedPermissions(Request $request): array
	{
		$user = $request->user();

		if (!$user) {
			return [
				'auth' => [
					'user'          => null,
					'jsPermissions' => null,
				],
			];
		}

		return [
			'auth' => [
				'user'          => $user,
				'jsPermissions' => method_exists($user, 'jsPermissions')
					? $user->jsPermissions()
					: null,
			],
		];
	}
}