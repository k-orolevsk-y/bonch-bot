<?php

	namespace Me\Korolevsky\BonchBot\Interfaces;

	use Me\Korolevsky\BonchBot\Api;

	interface Action {

		/**
		 * Action constructor.
		 * @param Api $api
		 * @param array $object
		 * @param array $payload
		 */
		public function __construct(Api $api, array $object, array $payload);

	}