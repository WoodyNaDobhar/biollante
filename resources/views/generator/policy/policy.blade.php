@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->policy }};

use Biollante\Helpers\BiollanteHelper;
use Biollante\Models\{{ $modelName }};
@if($modelName !== 'User')
use Biollante\Models\User;
@endif
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Str;

class {{ $modelName }}Policy
{
	use HandlesAuthorization;
	
	/**
	 * Perform pre-authorization checks.
	 *
	 * @param  \Biollante\Models\User|null  $user
	 * @param  string  $ability
	 * @return void|bool
	 */
	public function before(?User $user, $ability)
	{
		if ($user && $user->hasRole('Admin')) {
			return true;
		}
	}

	/**
	 * Determine whether the user can view any {{ Str::plural(Str::snake($modelName)) }}.
	 *
	 * @param  \Biollante\Models\User|null  $user
	 * @return mixed
	 */
	public function viewAny(?User $user)
	{
@if($hasListPermissions)
		if ($user && $user->can('list {{ Str::plural(Str::snake($modelName)) }}')) {
			return true;
		}
@else
		return true;
@endif
	}
	
	/**
	 * Determine whether the user can create {{ Str::plural(Str::snake($modelName)) }}.
	 *
	 * @param  \Biollante\Models\User|null  $user
	 * @return mixed
	 */
	public function create(?User $user)
	{
		if ($user && $user->can('store {{ Str::plural(Str::snake($modelName)) }}')) {
			return true;
		}
	}
@php
	$parentFields = [];
	foreach (['display', 'update', 'remove'] as $context) {
		$relationFields = ${$context . 'RelationFields'};
		foreach ($relationFields as $rf) {
			if (Str::endsWith($rf->path, '_id') && $rf->path === $rf->type . '_id') {
				$parentFields[$context] = (object)[
					'field' => $rf->path,
					'type'  => ucfirst($rf->type),
				];
				break;
			}
		}
	}

	$permissionTypes = [
		'view' => [
			'action' => 'display',
			'has' => $hasDisplayPermissions,
			'ownerFields' => $displayOwnerFields,
			'relationFields' => $displayRelationFields,
			'parentField' => $parentFields['display'] ?? null,
		],
		'update' => [
			'action' => 'update',
			'has' => $hasUpdatePermissions,
			'ownerFields' => $updateOwnerFields,
			'relationFields' => $updateRelationFields,
			'parentField' => $parentFields['update'] ?? null,
		],
		'delete' => [
			'action' => 'remove',
			'has' => $hasRemovePermissions,
			'ownerFields' => $removeOwnerFields,
			'relationFields' => $removeRelationFields,
			'parentField' => $parentFields['remove'] ?? null,
		],
	];
@endphp

@foreach($permissionTypes as $permType => $config)
	/**
	 * Determine whether the user can {{ $permType }} the {{ strtolower($modelName) }}.
	 *
	 * @param  \Biollante\Models\User|null  $user
	 * @param  \Biollante\Models\{{ $modelName }}  ${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}
	 * @return mixed
	 */
	public function {{ $permType }}(?User $user, {{ $modelName }} ${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }})
	{
@if($config['has'])
		if (
			$user && $user->can('{{ $config['action'] }} {{ Str::plural(Str::snake($modelName)) }}') @if(count($config['ownerFields']) > 0)||
			(
				$user && $user->can('{{ $config['action'] }}Own {{ Str::plural(Str::snake($modelName)) }}') &&
@if(count($config['ownerFields']) > 1)				(@endif
@foreach($config['ownerFields'] as $index => $ownerField)

@if(Str::endsWith($ownerField->path, '_id'))
@if((str_contains($ownerField->path, '->') ? substr($ownerField->path, 0, strrpos($ownerField->path, '->')) : $ownerField->path) === Str::plural(Str::singular(str_contains($ownerField->path, '->') ? substr($ownerField->path, 0, strrpos($ownerField->path, '->')) : $ownerField->path)))
					(
@if(str_contains($ownerField->path, '->'))						BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}, '{!! $ownerField->path !!}') &&
@endif						${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}->{!! preg_replace('/->[^->]+$/', '', $ownerField->path) !!}->contains(function (${{ preg_replace('/->[^->]+$/', '', $ownerField->path) === 'users' ? 'target' : Str::singular(preg_replace('/->[^->]+$/', '', $ownerField->path)) }}) use ($user) {
							return ${{ Str::singular(preg_replace('/->[^->]+$/', '', $ownerField->path)) }}->user_id === $user->id;
						})
					)@if(!$loop->last) ||@endif
@else
					(
@if(str_contains($ownerField->path, '->'))						BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) }}, '{!! $ownerField->path !!}') &&
@endif						${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}->{!! $ownerField->path !!} === $user->id
					)@if(!$loop->last) ||@endif
@endif
@elseif($ownerField->path !== '')
@if((str_contains($ownerField->path, '->') ? substr($ownerField->path, 0, strrpos($ownerField->path, '->')) : $ownerField->path) === Str::plural(Str::singular(str_contains($ownerField->path, '->') ? substr($ownerField->path, 0, strrpos($ownerField->path, '->')) : $ownerField->path)))
					(
						BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}, '{!! $ownerField->path !!}') &&
						${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}->{!! preg_replace('/->[^->]+$/', '', $ownerField->path) !!}->contains(function (${{ preg_replace('/->[^->]+$/', '', $ownerField->path) === 'users' ? 'target' : Str::singular(preg_replace('/->[^->]+$/', '', $ownerField->path)) }}) use ($user) {
@if(preg_replace('/->[^->]+$/', '', $ownerField->path) === 'users')
							return $target->id === $user->id;
@else
							return ${{ Str::singular(preg_replace('/->[^->]+$/', '', $ownerField->path)) }}->{!! Str::afterLast($ownerField->path, '->') !!}_type === 'User' &&
								${{ Str::singular(preg_replace('/->[^->]+$/', '', $ownerField->path)) }}->{!! Str::afterLast($ownerField->path, '->') !!}_id === $user->id;
@endif
						})
					)@if(!$loop->last) ||@endif
@else
					(
						BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}, '{!! $ownerField->path !!}') &&
						${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}->{!! $ownerField->path !!}_type === 'User' &&
						${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}->{!! $ownerField->path !!}_id === $user->id
					)@if(!$loop->last) ||@endif
@endif
@else
					(
						${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}->id === $user->id
					)@if(!$loop->last) ||@endif
@endif
@endforeach

@if(count($config['ownerFields']) > 1)
				)
@endif
			)@else
@endif @if(!empty($config['relationFields']))||
			(
				$user && $user->can('{{ $config['action'] }}Related {{ Str::plural(Str::snake($modelName)) }}') &&
				(
@foreach($config['relationFields'] as $index => $relationField)
					(
						isset($user->organizers) &&
						$user->organizers->contains(function ($relationOrganizer) use (${{ strtolower($modelName) }}) {
@if(Str::endsWith($relationField->path, '_id'))
@if(substr($relationField->path, 0, strrpos($relationField->path, '->')) !== '' && substr($relationField->path, 0, strrpos($relationField->path, '->')) === Str::plural(Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->')))))
							return $relationOrganizer->presideable_type === '{{ucfirst($relationField->type)}}' &&
@if(!Str::endsWith($relationField->path, strtolower($modelName) . '_id'))
								BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) }}, '{!! $relationField->path !!}') &&
								in_array($relationOrganizer->presideable_id, ${{ strtolower($modelName) }}->{!! str_contains($relationField->path, '->') ? substr($relationField->path, 0, strrpos($relationField->path, '->')) : $relationField->path !!}->pluck('{{Str::afterLast($relationField->path, '->')}}')->toArray());
@else
								BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) }}, '{!! str_contains($relationField->path, '->') ? (Str::plural(Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) . Str::studly(Str::replaceLast('_id', '', substr($relationField->path, strrpos($relationField->path, '->') + 2))))) : $relationField->path !!}') &&
								${{ strtolower($modelName) }}->{!! str_contains($relationField->path, '->') ? (Str::plural(Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) . Str::studly(Str::replaceLast('_id', '', substr($relationField->path, strrpos($relationField->path, '->') + 2))))) : $relationField->path !!}->contains(function (${{ Str::singular((str_contains($relationField->path, '->') ? substr($relationField->path, 0, strrpos($relationField->path, '->')) : $relationField->path)) }}) use ($relationOrganizer) {
									return $relationOrganizer->presideable_id === ${!! Str::singular((str_contains($relationField->path, '->') ? substr($relationField->path, 0, strrpos($relationField->path, '->')) : $relationField->path)) !!}->id;
								});
@endif
@else
							return $relationOrganizer->presideable_type === '{{ucfirst($relationField->type)}}' &&
								BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) }}, '{!! $relationField->path !!}') &&
								$relationOrganizer->presideable_id === ${{ strtolower($modelName) }}->{!! $relationField->path !!};
@endif
@else
@if(str_contains($relationField->path, '->') && substr($relationField->path, 0, strrpos($relationField->path, '->')) === Str::plural(Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->')))))
							return ${{ strtolower($modelName) }}->{!! substr($relationField->path, 0, strrpos($relationField->path, '->')) !!} && ${{ strtolower($modelName) }}->{!! substr($relationField->path, 0, strrpos($relationField->path, '->')) !!}->contains(function (${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}) use ($relationOrganizer) {
								return BiollanteHelper::instance()->safeIssetChain(${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}, '{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}') &&
@if(isset($config['parentField']))
									(
										(
											$relationOrganizer->presideable_type === ${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}_type &&
											$relationOrganizer->presideable_id === ${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}_id
										) ||
										(
											${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!} instanceof \Biollante\Models\Chapter && 
											isset(${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}->{{ $config['parentField']->field }}) &&
											$relationOrganizer->presideable_type === '{{ $config['parentField']->type }}' &&
											$relationOrganizer->presideable_id === ${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}->{{ $config['parentField']->field }}
										)
									);
@else
									$relationOrganizer->presideable_type === ${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}_type &&
									$relationOrganizer->presideable_id === ${{ Str::singular(substr($relationField->path, 0, strrpos($relationField->path, '->'))) }}->{!! substr($relationField->path, strrpos($relationField->path, '->') + 2) !!}_id;
@endif
								});
@else
@if(str_contains($relationField->path, '->'))
							return BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) }}, '{!! $relationField->path !!}') &&
@else
							return
@endif
@if($relationField->path === '')
								$relationOrganizer->presideable_type === '{{ucfirst($relationField->type)}}' &&
								$relationOrganizer->presideable_id === ${{ strtolower($modelName) }}->id;
@else
								$relationOrganizer->presideable_type === ${{ strtolower($modelName) }}->{!! $relationField->path !!}_type &&
								$relationOrganizer->presideable_id === ${{ strtolower($modelName) }}->{!! $relationField->path !!}_id;
@endif
@endif
@endif
						})
					)@if(!$loop->last) ||@endif

@endforeach
				)
			)
@endif
		) {
			return true;
		}
@else
		return true;
@endif
	}
	
@endforeach
	/**
	 * Determine whether the user can restore the {{ strtolower($modelName) }}.
	 *
	 * @param  \Biollante\Models\User|null  $user
	 * @param  \Biollante\Models\{{ $modelName }}  ${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}
	 * @return mixed
	 */
	public function restore(?User $user, {{ $modelName }} ${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }})
	{
		return false;
	}
	
	/**
	 * Determine whether the user can permanently delete the {{ strtolower($modelName) }}.
	 *
	 * @param  \Biollante\Models\User|null  $user
	 * @param  \Biollante\Models\{{ $modelName }}  ${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }}
	 * @return mixed
	 */
	public function forceDelete(?User $user, {{ $modelName }} ${{ strtolower($modelName) . ($modelName === 'User' ? 'Target' : '') }})
	{
		return false;
	}
}