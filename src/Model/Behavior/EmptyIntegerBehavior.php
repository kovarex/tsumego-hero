<?php

/**
 * EmptyIntegerBehavior
 *
 * Converts empty strings to appropriate values for integer columns:
 * - Empty string → 0 for NOT NULL integer columns
 * - Empty string → null for nullable integer columns
 *
 * This prevents database errors when saving form data with empty integer fields.
 */
class EmptyIntegerBehavior extends ModelBehavior {
	/**
	 * Cached schema information per model
	 *
	 * @var array
	 */
	protected $_schemaCache = [];

	/**
	 * Before save callback
	 *
	 * @param Model $model Model instance
	 * @param array $options Options array
	 * @return bool True to continue save, false to abort
	 */
	public function beforeSave(Model $model, $options = []) {
		if (empty($model->data[$model->alias])) {
			return true;
		}

		$schema = $this->_getSchema($model);

		foreach ($model->data[$model->alias] as $field => $value) {
			// Skip if field doesn't exist in schema
			if (!isset($schema[$field])) {
				continue;
			}

			$fieldSchema = $schema[$field];

			// Check if field is an integer type
			if (!$this->_isIntegerType($fieldSchema['type'])) {
				continue;
			}

			// Handle empty string
			if ($value === '') {
				// Convert empty string based on null constraint
				if (isset($fieldSchema['null']) && $fieldSchema['null'] === true) {
					// Nullable column: set to null
					$model->data[$model->alias][$field] = null;
				} else {
					// NOT NULL column: set to 0
					$model->data[$model->alias][$field] = 0;
				}
			} elseif (is_string($value) && !is_numeric($value)) {
				// Handle non-numeric strings
				// Log warning about non-numeric string
				CakeLog::warning(sprintf(
					'Non-numeric string "%s" provided for integer field %s.%s, converting to 0',
					$value,
					$model->alias,
					$field,
				));

				// Convert to 0 or null based on null constraint
				if (isset($fieldSchema['null']) && $fieldSchema['null'] === true) {
					$model->data[$model->alias][$field] = null;
				} else {
					$model->data[$model->alias][$field] = 0;
				}
			}
		}

		return true;
	}

	/**
	 * Get schema for model (with caching)
	 *
	 * @param Model $model Model instance
	 * @return array Schema array
	 */
	protected function _getSchema(Model $model) {
		$cacheKey = $model->alias;

		if (!isset($this->_schemaCache[$cacheKey])) {
			$this->_schemaCache[$cacheKey] = $model->schema();
		}

		return $this->_schemaCache[$cacheKey];
	}

	/**
	 * Check if a field type is an integer type
	 *
	 * @param string $type Field type from schema
	 * @return bool True if integer type
	 */
	protected function _isIntegerType($type) {
		$integerTypes = ['integer', 'biginteger', 'smallinteger', 'tinyinteger'];

		return in_array($type, $integerTypes);
	}

}
