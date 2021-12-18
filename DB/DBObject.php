<?php
	namespace Me\Korolevsky\Api\DB;

	use ArrayObject;

	class DBObject extends ArrayObject {

		protected array $object;
		protected array $__info;

		/**
		 * DBObject constructor.
		 * @param array|null $object
		 * @param string $table
		 */
		public function __construct(array|null $object, string $table) {
			$this->__info['table'] = $table;
			$this->object = $object;

			parent::__construct($object);
		}

	}