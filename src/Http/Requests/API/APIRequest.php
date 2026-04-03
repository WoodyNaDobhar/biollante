<?php

namespace Biollante\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class APIRequest extends FormRequest
{
	/**
	 * Get the proper failed validation response for the request.
	 *
	 * @param array $errors
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function response(array $errors)
	{
		$messages = implode(' ', Arr::flatten($errors));
		
		$res = [
			'success' => false,
			'message' => $messages,
		];

		return response()->json($res, Response::HTTP_BAD_REQUEST);
	}
}
