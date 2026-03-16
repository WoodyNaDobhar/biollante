@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->seeder }};

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class {{ $config->modelNames->name }}Seeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		// Turn off foreign key checks
		Schema::disableForeignKeyConstraints();

@if($config->tableName !== 'users')		\DB::table('{{ $config->tableName }}')->truncate();

@else		\DB::table('{{ $config->tableName }}')->where('id', '!=', 1)->delete();

@endif		\DB::table('{{ $config->tableName }}')->insert([
		{!! $seeds !!}
		]);
		
		// Turn on foreign key checks
		Schema::enableForeignKeyConstraints();
	}
}
