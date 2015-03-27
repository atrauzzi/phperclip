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

			$this->publishes([
				sprintf('%s/../config/config.php', __DIR__) => config_path('phperclip.php'),
				sprintf('%s/../config/filters.php', __DIR__) => config_path('phperclip_filters.php'),
			], 'config');

			$this->publishes([sprintf('%s/../database/migrations/', __DIR__) => base_path('/database/migrations')], 'migrations');

		}

	}

}