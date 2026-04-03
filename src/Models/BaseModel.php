<?php

namespace Biollante\Models;

use Carbon\Carbon;
use Biollante\Traits\CanGetTableNameStatically;
use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Models\Audit;

class BaseModel extends Model implements Auditable
{
	
	use \OwenIt\Auditing\Auditable;
	use PivotEventTrait;
	use CanGetTableNameStatically;

	public static function boot()
	{
		parent::boot();

		static::pivotAttaching(function ($model, $relationName, $pivotIds, $pivotIdsAttributes) {
			$audit = new Audit;
			$audit->role = 'User';
			$audit->user_id = Auth::check() ? Auth::user()->id : null;
			$audit->event = 'attached';
			$audit->auditable_type = get_class($model);
			$audit->auditable_id = $model->id;
			$audit->old_values = [];
			$audit->new_values = array(
				$pivotIds => $pivotIdsAttributes[0]
			);
			$audit->url = App::runningInConsole() ? 'console' : request()->fullUrl();
			$audit->ip_address = request()->ip();
			$audit->user_agent = request()->header('User-Agent');
			$audit->created_at = Carbon::now();
			$audit->save();
		});

		static::pivotDetaching(function ($model, $relationName, $pivotIds, $pivotIdsAttributes) {
			foreach($pivotIdsAttributes as $pivotIdsAttribute){
				$audit = new Audit;
				$audit->role = 'User';
				$audit->user_id = Auth::check() ? Auth::user()->id : null;
				$audit->event = 'detached';
				$audit->auditable_type = get_class($model);
				$audit->auditable_id = $model->id;
				$audit->old_values = array(
					$pivotIds => $pivotIdsAttribute
				);
				$audit->new_values = [];
				$audit->url = App::runningInConsole() ? 'console' : request()->fullUrl();
				$audit->ip_address = request()->ip();
				$audit->user_agent = request()->header('User-Agent');
				$audit->created_at = Carbon::now();
				$audit->save();
			}
		});
	}
}
