<?php

namespace Biollante\Generator\DTOs;

class GeneratorNamespaces
{
	public string $app;
	public string $repository;
	public string $model;
	public string $policy;
	public string $modelExtend;

	public string $seeder;
	public string $factory;

	public string $apiController;
	public string $apiResource;
	public string $apiRequest;

	public string $apiTests;
	public string $permissionTests;
	public string $repositoryTests;
	public string $unitTests;
	public string $tests;
}
