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
	protected $_schemaCache = array();

	/**
	 * Before save callback
	 *
	 * @param Model $Model Model instance
	 * @param array $options Options array
	 * @return bool True to continue save, false to abort
	 */
	public function beforeSave(Model $Model, $options = array()) {
		if (empty($Model->data[$Model->alias])) {
			return true;
		}

		$schema = $this->_getSchema($Model);

		foreach ($Model->data[$Model->alias] as $field => $value) {
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
					$Model->data[$Model->alias][$field] = null;
				} else {
					// NOT NULL column: set to 0
					$Model->data[$Model->alias][$field] = 0;
				}
			}
			// Handle non-numeric strings
			elseif (is_string($value) && !is_numeric($value)) {
				// Log warning about non-numeric string
				CakeLog::warning(sprintf(
					'Non-numeric string "%s" provided for integer field %s.%s, converting to 0',
					$value,
					$Model->alias,
					$field
				));

				// Convert to 0 or null based on null constraint
				if (isset($fieldSchema['null']) && $fieldSchema['null'] === true) {
					$Model->data[$Model->alias][$field] = null;
				} else {
					$Model->data[$Model->alias][$field] = 0;
				}
			}
		}

		return true;
	}

	/**
	 * Get schema for model (with caching)
	 *
	 * @param Model $Model Model instance
	 * @return array Schema array
	 */
	protected function _getSchema(Model $Model) {
		$cacheKey = $Model->alias;

		if (!isset($this->_schemaCache[$cacheKey])) {
			$this->_schemaCache[$cacheKey] = $Model->schema();
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
		$integerTypes = array('integer', 'biginteger', 'smallinteger', 'tinyinteger');
		return in_array($type, $integerTypes);
	}
}
