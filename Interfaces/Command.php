<?php
	namespace Me\Korolevsky\BonchBot\Interfaces;

	use Me\Korolevsky\BonchBot\Api;

	interface Command {

		/**
		 * Command constructor.
		 * @param Api $api
		 * @param array $object
		 */
		public function __construct(Api $api, array $object);

	}