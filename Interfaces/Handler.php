<?php
	namespace Me\Korolevsky\BonchBot\Interfaces;

	use Me\Korolevsky\BonchBot\VKApi;

	interface Handler {

		/**
		 * Handler constructor.
		 * @param VKApi $vkApi
		 * @param array $object
		 */
		public function __construct(VKApi $vkApi, array $object);

	}