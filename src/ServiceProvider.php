<?php namespace Atrauzzi\Phperclip {


	class ServiceProvider extends \Illuminate\Support\ServiceProvider {

		/**
		 *
		 */
		public function register() {

			$this->registerPackage();

		}

		/**
		 *
		 */
		public function boot() {

		}

		//
		//
		//

		/**
		 *
		 */
		protected function registerPackage() {

			$this->app->alias('filesystem', 'Illuminate\Filesystem\FilesystemManager');

			$this->publishes([
				sprintf('%s/../config/config.php', __DIR__) => config_path('phperclip.php'),
			], 'config');

			$this->publishes([sprintf('%s/../database/migrations/', __DIR__) => base_path('/database/migrations')], 'migrations');

		}

	}

}