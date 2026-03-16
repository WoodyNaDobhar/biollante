<?php

namespace Biollante\Helpers;

use Biollante\Common\FileSystem;
use Illuminate\Support\Str;

class BiollanteHelper
{
	public function g_filesystem(): FileSystem
	{
		return app(FileSystem::class);
	}

	public static function format_tab(): string
	{
		return "\t";
	}

	public static function format_tabs(int $tabs): string
	{
		return str_repeat(self::format_tab(), $tabs);
	}

	public static function format_nl(int $count = 1): string
	{
		return str_repeat(PHP_EOL, $count);
	}

	public static function format_nls(int $count, int $nls = 1): string
	{
		return str_repeat(self::format_nl($nls), $count);
	}

	public static function format_nl_tab(int $lns = 1, int $tabs = 1): string
	{
		return self::format_nls($lns) . self::format_tabs($tabs);
	}

	public static function model_name_from_table_name(string $tableName): string
	{
		return Str::ucfirst(Str::camel(Str::singular($tableName)));
	}

	public static function instance(): self
	{
		return new self();
	}
}