@php
	echo '<?php'.PHP_EOL;
	$nl = PHP_EOL;
@endphp

namespace {{ $appNamespace }}\Http\Controllers\API;

use Throwable;
use {{ $appNamespace }}\Http\Controllers\AppBaseController;
use {{ $modelNamespace }}\User;
@foreach($relatedModels as $model => $fields)
use {{ $modelNamespace }}\{{ $model }};
@endforeach
@if($agreements)
use {{ $modelNamespace }}\{{ $agreements['agreement_model'] }};
@foreach($agreements['supporting_models'] ?? [] as $model => $modelConfig)
use {{ $modelNamespace }}\{{ $model }};
@endforeach
@if($agreements['context_model'] ?? null)
use {{ $modelNamespace }}\{{ $agreements['context_model'] }};
@endif
@endif
@if($invitationsEnabled)
use {{ $modelNamespace }}\Invitation;
@endif
@if($searchEnabled)
@foreach($searchConfig['models'] ?? [] as $model => $opts)
use {{ $modelNamespace }}\{{ $model }};
@endforeach
@endif
use {{ $repoNamespace }}\UserRepository;
@foreach($repositories as $repo)
@if($repo !== 'User')
use {{ $repoNamespace }}\{{ $repo }}Repository;
@endif
@endforeach
use Biollante\Helpers\BiollanteHelper;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
@if($invitationsEnabled)
use Illuminate\Support\Facades\Crypt;
@endif
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Passwords\PasswordBrokerManager;

/**
 * Class BaseAPIController
 * @package {{ $appNamespace }}\Http\Controllers\API
 */

class BaseAPIController extends AppBaseController
{
	
@foreach($repositories as $repo)
	/** @var {{ $repo }}Repository */
	private ${{ lcfirst($repo) }}Repository;

@endforeach
	public function __construct(
@foreach($repositories as $repo)
		{{ $repo }}Repository ${{ lcfirst($repo) }}Repo{{ $loop->last ? '' : ',' }}
@endforeach
	)
	{
@foreach($repositories as $repo)
		$this->{{ lcfirst($repo) }}Repository = ${{ lcfirst($repo) }}Repo;
@endforeach
	}

@if($invitationsEnabled)
	/**
	 * @@OA\Post(
	 *		path="/generateInvite",
	 *		summary="Generate an invitation token.",
	 *		security={ {"bearer_token":{}} },
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		requestBody={
	 *			@@OA\MediaType(
	 *				mediaType="multipart/form-data",
	 *				@@OA\Schema(
	 *					required={"type","id"},
	 *					@@OA\Property(property="type", description="Type of invitation.", type="string", example="default"),
	 *					@@OA\Property(property="id", description="ID of the target.", type="integer", example=42),
	 *					@@OA\Property(property="referer_id", description="Referrer ID (optional).", type="integer", example=99),
	 *					@@OA\Property(property="expires_at", description="Expiry date (optional).", type="string", example="2025-12-31"),
	 *					@@OA\Property(property="usage_limit", description="Max uses (optional).", type="integer", example=50),
	 *					@@OA\Property(property="utm_source", description="UTM source (optional).", type="string"),
	 *					@@OA\Property(property="utm_medium", description="UTM medium (optional).", type="string"),
	 *					@@OA\Property(property="utm_campaign", description="UTM campaign (optional).", type="string")
	 *				)
	 *			)
	 *		},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=401, description="unauthenticated"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function generateInvite(Request $request)
	{
		try{
			
			$user = Auth::user();
			
			if (count($user->tokens()->get()->toArray()) < 1) {
				return $this->sendError('Unauthenticated.', null, 401);
			}
			
			$request->validate(User::$inviteRules, User::$messages);
			
			$input = $request->all();

			$token = Crypt::encryptString(json_encode($input));
			
			return $this->sendResponse($token, 'Token encoded.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	/**
	 * @@OA\Post(
	 *		path="/decodeInvite",
	 *		summary="Decrypt an invitation token.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={},
	 *		requestBody={
	 *			@@OA\MediaType(
	 *				mediaType="multipart/form-data",
	 *				@@OA\Schema(
	 *					required={"token"},
	 *					@@OA\Property(property="token", description="Encrypted invite token.", type="string")
	 *				)
	 *			)
	 *		},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function decodeInvite(Request $request)
	{
		try {
			$request->validate(User::$decodeInviteRules, User::$messages);
			$payload = $this->decodeInviteToken($request->input('token'));
			return $this->sendResponse($payload, 'Token decoded.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	/**
	 * Decrypt and parse the invite token.
	 */
	private function decodeInviteToken(string $token): array
	{
		try {
			$decrypted = Crypt::decryptString($token);
			$payload = json_decode($decrypted, true);
			return $payload;
		} catch (\Throwable $e) {
			throw new \Exception("Failed to decode invite token: " . $e->getMessage());
		}
	}
@endif

	/**
	 * @@OA\Post(
	 *		path="/register",
	 *		summary="Register to the site.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={},
	 *		requestBody={"$ref": "#/components/requestBodies/register"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function register(Request $request)
	{
		try {
			\DB::beginTransaction();
			$input = $request->all();

@if($invitationsEnabled)
			// Handle invite-based enforcement
			if (!empty($input['{{ $invitationTokenField }}'])) {
				try {
					$invite = $this->decodeInviteToken($input['{{ $invitationTokenField }}']);

					if (!empty($invite['expires_at']) && strtotime($invite['expires_at']) < time()) {
						throw new \Exception("This invitation has expired.");
					}
					if (!empty($invite['usage_limit'])) {
						$count = User::where('{{ $invitationTokenField }}', $input['{{ $invitationTokenField }}'])->count();
						if ($count >= $invite['usage_limit']) {
							throw new \Exception("This invitation has reached its usage limit.");
						}
					}
					// Copy over any UTM fields
					foreach (['utm_source','utm_medium','utm_campaign'] as $utm) {
						if (!empty($invite[$utm])) {
							$input[$utm] = $invite[$utm];
						}
					}
				} catch (\Throwable $e) {
					throw new \Exception("Invalid invitation: " . $e->getMessage());
				}
			}
@endif

			// Check if email already exists
			$existingUser = User::where('email', $input['email'])->first();
			if ($existingUser) {
				if (!Hash::check($input['password'], $existingUser->password)) {
					throw ValidationException::withMessages([
						'email' => ['The email is already taken, and the provided credentials are incorrect.'],
					]);
				}
				if (!$existingUser->is_active) {
					throw ValidationException::withMessages([
						'active' => ['The email is already taken, and the account is invalid.'],
					]);
				}
				$user = $existingUser;
			}

			// If no existing user, create one
			if (empty($user)) {
				$request->validate((new User())->getCreateRules(), User::$messages);
@if(!empty($userDefaults))

				// Apply forced defaults
@foreach($userDefaults as $field => $value)
				$input['{{ $field }}'] = {{ is_string($value) ? "'" . $value . "'" : $value }};
@endforeach
@endif

				$user = $this->userRepository->create($input);
			}

@if(!empty($relatedModels))
			// Create related models
@foreach($relatedModels as $model => $fields)
@php
			$modelVar = lcfirst($model);
			$table = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($model));
@endphp
			if (
@foreach($fields as $field => $opts)
@if($opts['required'] ?? false)
				!empty($input['{{ $field }}']){{ $loop->last ? '' : ' &&' }}
@endif
@endforeach
			) {
				${{ $modelVar }}Data = [
					'user_id' => $user->id,
@foreach($fields as $field => $opts)
					'{{ $field }}' => $input['{{ $field }}'] ?? null,
@endforeach
					'created_by' => $user->id,
				];
				{{ $model }}::create(${{ $modelVar }}Data);
			}

@endforeach
@endif
@if($agreements)
			// Process agreements/waivers
@php
			$contextModel = $agreements['context_model'];
			$contextField = $agreements['context_field'];
			$agreementModel = $agreements['agreement_model'];
			$contextVar = lcfirst($contextModel);
@endphp
			if (!empty($input['{{ $contextField }}'])) {
				${{ $contextVar }} = {{ $contextModel }}::findOrFail($input['{{ $contextField }}']);

@foreach($agreements['supporting_models'] ?? [] as $supportModel => $supportConfig)
@php
				$supportVar = lcfirst($supportModel);
@endphp
				${{ $supportVar }} = {{ $supportModel }}::create([
@foreach($supportConfig['fields'] as $field)
					'{{ $field }}' => $input['{{ $field }}'] ?? null,
@endforeach
					'created_by' => $user->id,
				]);

@endforeach
				$waiverData = [
					'agreeable_type' => 'User',
					'agreeable_id'   => $user->id,
					'context_type'   => '{{ $contextModel }}',
					'context_id'     => ${{ $contextVar }}->id,
@foreach($agreements['supporting_models'] ?? [] as $supportModel => $supportConfig)
					'{{ $supportConfig['fk_field'] }}' => ${{ lcfirst($supportModel) }}->id,
@endforeach
@foreach($agreements['computed_fields'] ?? [] as $targetField => $sourceExpr)
@if(str_contains($sourceExpr, '+'))
					'{{ $targetField }}' => {!! collect(explode('+', $sourceExpr))->map(fn($part) => str_contains(trim($part), "'") ? trim($part) : '$input[\'' . trim($part) . '\']')->implode(' . ') !!},
@else
					'{{ $targetField }}' => $input['{{ $sourceExpr }}'] ?? null,
@endif
@endforeach
@foreach($agreements['data_fields'] ?? [] as $field)
					'{{ $field }}' => $input['{{ $field }}'] ?? null,
@endforeach
					'signed_on'   => now(),
					'created_by'  => $user->id,
				];

				foreach ({!! json_encode($agreements['waiver_types']) !!} as $type) {
					if (${{ $contextVar }}->{"is_waiver_" . strtolower($type)} && !empty($input[strtolower($type) . '_signed'])) {
						{{ $agreementModel }}::create([...$waiverData, 'variety' => $type]);
					}
				}
			}
@endif

@if($defaultRole)
			// Assign default role
			if (method_exists($user, 'assignRole')) {
				$user->assignRole('{{ $defaultRole }}');
			}
@endif

			\DB::commit();

			// Auto-login
			return $this->login($request);
		}
		catch (\Throwable $e) {
			\DB::rollBack();
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__,'file',$e->getTrace())];
			Log::error($e->getMessage() . " ({$trace['file']}:{$trace['line']})\n" . $e->getTraceAsString());
			return $this->sendError(
				$e->getMessage(),
				$e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(),
				$e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400
			);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/login",
	 *		summary="Get auth token.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={},
	 *		requestBody={"$ref": "#/components/requestBodies/login"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function login(Request $request)
	{
		try {
			
			$request->validate(User::$loginRules, User::$messages);
			
			$user = User::where('email', $request->email)
@foreach($loginWith as $relation)
				->with('{{ $relation }}')
@endforeach
				->first();

			if (! $user || ! Hash::check($request->password, $user->password)) {
				throw ValidationException::withMessages([
					'email' => ['The provided credentials are incorrect.'],
				]);
			}

			if (!$user->is_active) {
				throw ValidationException::withMessages([
					'active' => ['The account is invalid.'],
				]);
			}
			
			$userArray = $user->toArray();
			if (method_exists($user, 'jsPermissions')) {
				$userArray['jsPermissions'] = $user->jsPermissions();
			}
			$userArray['token'] = explode('|', $user->createToken($request->device_name)->plainTextToken)[1];
			
			return $this->sendResponse($userArray, 'Login successful.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), null, $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Get(
	 *		path="/logout",
	 *		summary="Delete auth token.",
	 *		security={ {"bearer_token":{}} },
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=401, description="unauthenticated"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function logout()
	{
		try {
			
			$user = Auth::user();
			
			if (count($user->tokens()->get()->toArray()) < 1) {
				return $this->sendError('Unauthenticated.', null, 401);
			}

			$user->tokens()->delete();
			
			return $this->sendResponse(null, 'Logout successful.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), null, $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/check",
	 *		summary="Check if a given User's auth token is still active.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		requestBody={"$ref": "#/components/requestBodies/check"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function check(Request $request)
	{
		try {
			
			$request->validate(User::$checkRules, User::$messages);
			
			$user = User::where('id', $request->user_id)->first();
			
			if (!$user || count($user->tokens()->get()->toArray()) < 1) {
				return $this->sendResponse(false, 'Check successful.', null, false);
			}
			
			foreach($user->tokens()->get() as $token){
				if (Carbon::parse($token->created_at)->addMinutes(20)->greaterThanOrEqualTo(Carbon::now())) {
					return $this->sendResponse(true, 'Check successful.');
				}
			}
			
			return $this->sendResponse(false, 'Check successful.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/checkpass",
	 *		summary="Check if the logged in user knows their password.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		requestBody={"$ref": "#/components/requestBodies/checkpass"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function checkpass(Request $request)
	{
		try {
			
			$request->validate(User::$checkPassRules, User::$messages);
			
			$user = Auth::user();
			
			if (!Hash::check($request->password, $user->password)) {
				return $this->sendError('The provided credentials are incorrect.');
			}
			
			return $this->sendResponse(true, 'The provided credentials are correct.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/forgot",
	 *		summary="Send reset password email to user.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={},
	 *		requestBody={"$ref": "#/components/requestBodies/forgot"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function forgot(Request $request)
	{
		try {
			
			$request->validate(User::$forgotRules, User::$messages);
			
			$response = "Assuming it's in our system, a password reset link has been sent to the given email address.";
			
			$user = User::where('email', $request->email)->first();
			
			if (!$user) {
				return $this->sendSuccess($response);
			}
			
			$passwordBrokerManager = app(PasswordBrokerManager::class);
			$passwordBroker = $passwordBrokerManager->broker();
			
			$token = $passwordBroker->getRepository()->create($user);
			
			$user->sendPasswordResetNotification($token);
			
			return $this->sendSuccess($response);
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/reset",
	 *		summary="Change User password. Returns auth token.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={},
	 *		requestBody={"$ref": "#/components/requestBodies/reset"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function reset(Request $request)
	{
		try {
			
			$user = User::where('email', $request->input('email'))->first();
			
			if (!$user) {
				return $this->sendError('The provided email is incorrect.', null, 400);
			}

			$request->validate(User::getSetPasswordRules($user), User::$messages);
			
			$passwordBrokerManager = app(PasswordBrokerManager::class);
			$passwordBroker = $passwordBrokerManager->broker();
			
			$credentials = $request->only('email', 'password', 'password_confirmation');
			$credentials['token'] = $request->input('token');

			$status = $passwordBroker->reset(
				$credentials,
				function ($user, $password) use($request) {
					$user->forceFill([
						'password' => Hash::make($password)
					])->setRememberToken(Str::random(60));
					$user->save();
					event(new PasswordReset($user));
				}
			);

			if($status !== Password::PASSWORD_RESET){
				return $this->sendError(User::$messages[$status] ?? 'Password reset failed.', null, 400);
			}
			
			return $this->login($request);
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/resend",
	 *		summary="Resend the user's email verification link.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={ {"bearer_token":{}} },
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function resend(Request $request)
	{
		try {
			
			$user = Auth::user();
			
			if (! $user ) {
				throw ValidationException::withMessages([
					'auth' => ['You are not logged in.'],
				]);
			}
			
			if ( $user->email_verified_at ) {
				return $this->sendError('Your email has already been verified.', null, 400);
			}
			
			$request->user()->sendEmailVerificationNotification();
			
			return $this->sendSuccess("Your email verification link has been resent.");
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
	
	/**
	 * @@OA\Post(
	 *		path="/email/verify",
	 *		summary="Verify the user's email using a verification hash.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={ {"bearer_token":{}} },
	 *		requestBody={
	 *			@@OA\MediaType(
	 *				mediaType="multipart/form-data",
	 *				@@OA\Schema(
	 *					required={"hash"},
	 *					@@OA\Property(property="hash", description="The email verification hash.", type="string")
	 *				)
	 *			)
	 *		},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function verify(Request $request)
	{
		try {
			
			$user = Auth::user();
			
			if (! $user ) {
				throw ValidationException::withMessages([
					'auth' => ['You are not logged in.'],
				]);
			}

			if (sha1($user->getEmailForVerification()) !== $request->hash) {
				throw ValidationException::withMessages([
					'verification' => ['Invalid verification link.'],
				]);
			}
			
			if (!$user->hasVerifiedEmail()) {
				$user->markEmailAsVerified();
				event(new Verified($user));
				return $this->sendSuccess("Your email has been verified.");
			} else {
				return $this->sendError('Your email has already been verified.', null, 400);
			}
			
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

@if($searchEnabled)
	/**
	 * @@OA\Post(
	 *		path="/search",
	 *		summary="Search common predetermined models for keywords.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		requestBody={"$ref": "#/components/requestBodies/search"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function search(Request $request)
	{
		try {

			$response = [];
@foreach($searchConfig['models'] ?? [] as $model => $opts)
@php
			$plural = \Illuminate\Support\Str::plural($model);
			$withList = $opts['with'] ?? [];
@endphp
@if(empty($withList))
			$response['{{ $plural }}'] = {{ $model }}::search($request->search)->get();
@else
			$response['{{ $plural }}'] = {{ $model }}::search($request->search)->query(function ($builder) {
@foreach($withList as $with)
				$builder->with('{{ $with }}');
@endforeach
			})->get();
@endif
@endforeach

			return $this->sendResponse($response, 'Search complete.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
@endif

@if($invitationsEnabled)
	/**
	 * @@OA\Post(
	 *		path="/sendInvite",
	 *		summary="Send an invitation.",
	 *		security={ {"bearer_token":{}} },
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		requestBody={"$ref": "#/components/requestBodies/sendInvite"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=401, description="unauthenticated"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function sendInvite(Request $request)
	{
		try {
			
			$user = Auth::user();

			if (! $user ) {
				throw ValidationException::withMessages([
					'auth' => ['You are not logged in.'],
				]);
			}
			
			$request->validate(User::$sendInviteRules, User::$messages);

			$input = $request->all();
			$input['sent_by'] = $user->id;

			$invitation = Invitation::create($input);
			
			return $this->sendResponse([], 'Invitation sent successfully.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	/**
	 * @@OA\Post(
	 *		path="/accept",
	 *		summary="Accept an invitation.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		requestBody={"$ref": "#/components/requestBodies/accept"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function accept(Request $request)
	{
		try {
			
			$user = Auth::user();

			if (! $user ) {
				throw ValidationException::withMessages([
					'auth' => ['You are not logged in.'],
				]);
			}
			
			$request->validate(User::$acceptInviteRules, User::$messages);
			
			$invitation = Invitation::where('id', Crypt::decryptString($request->input('{{ $invitationTokenField }}')))->first();
			
			if(!$invitation){
				throw ValidationException::withMessages([
					'{{ $invitationTokenField }}' => ['The provided credentials are incorrect.'],
				]);
			}

			// Process acceptance — consuming app extends this via Extension pattern
			$invitation->delete();
			
			return $this->sendResponse([], 'Invitation accepted successfully.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
@endif

@if($deleteEnabled)
	/**
	 * @@OA\Post(
	 *		path="/delete",
	 *		summary="Remove your registration. Personal data will be anonymized and login access revoked. This cannot be undone.",
	 *		tags={"Base"},
	 *		description="{{ $accessDescription }}",
	 *		security={ {"bearer_token":{}} },
	 *		requestBody={"$ref": "#/components/requestBodies/delete"},
	 *		@@OA\Response(response=200, description="successful operation"),
	 *		@@OA\Response(response=400, description="unsuccessful operation"),
	 *		@@OA\Response(response=403, description="unauthorized")
	 *	)
	 */
	public function delete(Request $request)
	{
		try {
			
			$user = Auth::user();
			
			$request->validate(User::$deleteRules, User::$messages);
			
			if (!Hash::check($request->password, $user->password)) {
				return $this->sendError('The provided credentials are incorrect.');
			}
			
			$user->tokens()->delete();

			$user->update([
@foreach($deleteAnonymize as $field => $value)
@if(is_null($value))
				'{{ $field }}' => null,
@elseif(is_bool($value))
				'{{ $field }}' => {{ $value ? 'true' : 'false' }},
@elseif(is_string($value) && str_contains($value, '{id}'))
				'{{ $field }}' => str_replace('{id}', $user->id, '{!! $value !!}'),
@elseif(is_string($value))
				'{{ $field }}' => '{!! $value !!}',
@else
				'{{ $field }}' => {{ $value }},
@endif
@endforeach
			]);
			
			return $this->sendResponse(null, 'Account deletion successful.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
@endif
}
