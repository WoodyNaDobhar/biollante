@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->model }}\Core;

use Illuminate\Database\Eloquent\Model;
@if($config->options->softDelete)
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
@endif
@if($config->options->tests || $config->options->factory)
use Illuminate\Database\Eloquent\Factories\HasFactory;
@endif
use Biollante\Traits\CustomAuditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
@if($config->modelNames->name === "User")
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;
use Laravel\Cashier\Billable;
@endif
@if($config->modelNames->name === "Subscription")
use Laravel\Cashier\Subscription as CashierSubscription;
@endif
@if($config->modelNames->name === "SubscriptionItem")
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;
@endif

@if($config->modelNames->name === "User")
class {{ $config->modelNames->name }} extends Authenticatable implements AuditableContract, MustVerifyEmail
@elseif($config->modelNames->name === "Subscription")
class {{ $config->modelNames->name }} extends CashierSubscription implements AuditableContract
@elseif($config->modelNames->name === "SubscriptionItem")
class {{ $config->modelNames->name }} extends CashierSubscriptionItem implements AuditableContract
@else
class {{ $config->modelNames->name }} extends BaseModel implements AuditableContract
@endif
{

@if($config->modelNames->name === "User")
	use HasRoles;
	use HasApiTokens;
	use HasProfilePhoto;
	use Notifiable;
	use TwoFactorAuthenticatable;
	use LaravelPermissionToVueJS;
	use Billable;
@endif
	use CustomAuditable;
@if($config->options->softDelete)
{{ Biollante\Helpers\BiollanteHelper::format_tab().'use SoftDeletes;' }}
{{ Biollante\Helpers\BiollanteHelper::format_tab().'use Userstamps;' }}
@endif
@if($config->options->tests or $config->options->factory)
{{ Biollante\Helpers\BiollanteHelper::format_tab().'use HasFactory;' }}
@endif

	/**
	 * The database table used by the model.
	 * @var string
	 */
	public $table = '{{ $config->tableName }}';
@if($config->connection)@tab()protected $connection = '{{ $config->connection }}';@nls(2)@endif
@if(!$timestamps)@tab()public $timestamps = false;@nls(2)@endif
@if($customSoftDelete)@tab()protected $dates = ['{{ $customSoftDelete }}'];@nls(2)@endif
@if($customCreatedAt)@tab()const CREATED_AT = '{{ $customCreatedAt }}';@nls(2)@endif
@if($customUpdatedAt)@tab()const UPDATED_AT = '{{ $customUpdatedAt }}';@nls(2)@endif
@if($config->modelNames->name === "User")
	
	protected $guard_name = 'api';
	protected function getDefaultGuardName(): string { return 'api'; }
@endif

	/**
	 * Fields that are mass assignable.
	 * @var array
	 */
	public $fillable = [
		{!! $fillables !!}
	];

	/**
	 * The attributes that cannot be null.
	 * @var array<int, string>
	 */
	public $required = [
		{!! $required !!}
	];

	/**
	 * The attributes that should be hidden for serialization.
	 * @var array<int, string>
	 */
	protected $hidden = [
		{!! $hidden !!}
	];

	/**
	 * The data type casting for the model\'s attributes.
	 * @var array
	 */
	protected $casts = [
		{!! $casts !!}
	];

@if($hasSlug)
	/**
	 * Slug handling.
	 *
	 * @param  mixed  $value
	 * @param  string|null  $field
	 * @return \Illuminate\Database\Eloquent\Model|null
	 */
	public function resolveRouteBinding($value, $field = null)
	{
		$value = (string)$value;

		if (ctype_digit($value)) {
			return $this->newQuery()->where('id', (int)$value)->firstOrFail();
		}

		return $this->newQuery()->where('slug', $value)->firstOrFail();
	}
@endif

	/**
	 * Validation rules for the model attributes.
	 * @var array
	 */
	public function getCreateRules(): array
	{
		return [
			{!! $createRules !!}
		];
	}
	public function getUpdateRules(): array
	{
		return [
			{!! $updateRules !!}
		];
	}

	/**
	 * Model relationships.
	 * @var array
	 */
	public static array $relationships = [
		{!! $relationships !!}
	];

@if($autoOrders)	/**
	 * Auto-order by retr/order field
	 */
	{!! $autoOrders !!}

@endif	/**
	 * Relationship definitions.
	 */
	
	{!! $relations !!}
}
