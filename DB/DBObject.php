<?php
	namespace Me\Korolevsky\Api\DB;

	final class DBObject extends \ArrayObject {

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

		public function changeId(int|string $id): int|string {
			$this->__info['id'] = $id;
			return $this->__info['id'];
		}

		public function getInfo(string $key): mixed {
			return @$this->__info[$key];
		}

		public function setInfo(string $key, mixed $value): mixed {
			$this->__info[$key] = $value;
			return $this->__info[$key];
		}

	}