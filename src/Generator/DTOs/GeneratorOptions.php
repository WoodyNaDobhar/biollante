<?php

namespace Biollante\Generator\DTOs;

class GeneratorOptions
{
	public bool $softDelete;
	public bool $saveSchemaFile;
	public bool $localized;
	public bool $repositoryPattern;
	public bool $resources;
	public bool $factory;
	public bool $seeder;
	public bool $swagger;
	public bool $tests;
	public bool $auditable;
	public bool $userstamps;
	public array $excludedFields;
	public array $excludedTables;
}
