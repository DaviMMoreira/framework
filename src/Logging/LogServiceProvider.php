<?php
/**
 * This file is part of the Autarky package.
 *
 * (c) Andreas Lutro <anlutro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Autarky\Logging;

use Autarky\Kernel\ServiceProvider;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class LogServiceProvider extends ServiceProvider
{
	public function register()
	{
		$this->bindLogger();
		$this->registerErrorHandler();
	}

	public function bindLogger()
	{
		$this->app->getContainer()->share('Monolog\Logger', function() {
			$logger = new Logger($this->app->getEnvironment());

			if ($logdir = $this->app->getConfig()->get('path.logs')) {
				if (!is_dir($logdir)) {
					throw new \RuntimeException("Log directory $logdir does not exist or is not a directory.");
				}

				$logpath = rtrim($logdir, '\\/').'/'.php_sapi_name().'.log';

				if (file_exists($logpath) && !is_writable($logpath)) {
					throw new \RuntimeException("Log file $logpath is not writeable.");
				}

				$handler = new StreamHandler($logpath, Logger::DEBUG);
				$handler->setFormatter(new LineFormatter(null, null, true));
				$logger->pushHandler($handler);
			}

			return $logger;
		});

		$this->app->getContainer()
			->alias('Psr\Log\LoggerInterface', 'Monolog\Logger');
	}

	public function registerErrorHandler()
	{
		$this->app->getErrorHandler()
			->prependHandler(function($exception) {
				$request = $this->app->getRouter()->getCurrentRequest();
				$route = $this->app->getRouter()->getCurrentRoute();
				$context = [
					'method' => $request->getMethod(),
					'uri' => $request->getRequestUri(),
					'name' => $route->getName() ?: 'Unnamed route',
				];

				$this->app->getContainer()->resolve('Psr\Log\LoggerInterface')
					->error($exception, $context);
			});
	}
}
