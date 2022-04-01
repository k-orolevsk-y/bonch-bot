<?php
	namespace Me\Korolevsky\BonchBot\Actions;

	use Me\Korolevsky\BonchBot\Api;

	class Definition {

		public function __construct(Api $api, array $object, array $payload) {
			switch($payload['action']) {
				case "order":
					return new Order($api, $object, $payload);
				case "schedule_teacher":
					return new ScheduleTeacher($api, $object, $payload);
			}
		}

	}