<?php

namespace Biollante\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Converts not-found exceptions on JSON-expecting requests into the
 * Biollante API response envelope: {success: false, message: "..."} with
 * HTTP 404.
 *
 * Registered automatically on Laravel's `api` middleware group by
 * BiollanteServiceProvider::boot(). Non-JSON requests pass through
 * untouched so Laravel's default rendering still applies.
 */
class HandleApiNotFound
{
	public function handle(Request $request, Closure $next)
	{
		try {
			return $next($request);
		} catch (ModelNotFoundException $e) {
			if ($request->expectsJson()) {
				return response()->json([
					'success' => false,
					'message' => class_basename($e->getModel()) . ' not found.',
				], 404);
			}
			throw $e;
		} catch (NotFoundHttpException $e) {
			if ($request->expectsJson()) {
				return response()->json([
					'success' => false,
					'message' => $e->getPrevious()?->getMessage() ?? 'Record not found.',
				], 404);
			}
			throw $e;
		}
	}
}