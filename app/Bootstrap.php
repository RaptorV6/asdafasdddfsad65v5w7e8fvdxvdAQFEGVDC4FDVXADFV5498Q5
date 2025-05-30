<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;


class Bootstrap
{
	public static function boot(): Configurator
	{
		$configurator = new Configurator;
		$appDir = dirname(__DIR__);

		$configurator->setDebugMode(true); // enable for your remote IP
        $configurator->enableDebugger(__DIR__. '/../log');
		$configurator->enableTracy($appDir . '/log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($appDir . '/temp');

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();
                \Kdyby\Replicator\Container::register();
		$configurator->addConfig($appDir . '/config/common.neon');
                
                $configurator->onCompile[] = function ($configurator, $compiler) {
                    $compiler->addExtension('dibiPostgre', new \Dibi\Bridges\Nette\DibiExtension22);
                };

		return $configurator;
	}
}
