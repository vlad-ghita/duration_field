<?php

	Class FieldDuration extends Field {

		private $durSettings = array('weeks', 'days', 'hours', 'minutes', 'seconds', 'fractions');



		/*------------------------------------------------------------------------------------------------*/
		/*  Initialisation  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct() {
			parent::__construct();

			$this->_name     = 'Duration';
			$this->_required = true;
		}

		public function isSortable() {
			return true;
		}

		public function canFilter() {
			return true;
		}

		public function createTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT null auto_increment,
				  `entry_id` int(11) unsigned NOT null,
				  `value` double unsigned,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		public function allowDatasourceParamOutput() {
			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function set($field, $value) {
			if ($field == 'settings' && !is_array($value)) {
				$value = explode(',', $value);
			}

			$this->_fields[$field] = $value;
		}

		public function findDefaults(array &$settings) {
			if (!isset($settings['settings'])) {
				$settings['settings'] = $this->durSettings;
			}

			if (!isset($settings['required'])) {
				$settings['required'] = 'yes';
			}
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$cols = new XMLElement('div', null, array('class' => 'three columns'));

			$this->appendRequiredCheckbox($cols);
			$this->appendShowColumnCheckbox($cols);
			$this->appendChooseSettings($cols);

			$wrapper->appendChild($cols);
		}

		protected function appendChooseSettings(XMLElement &$wrapper) {
			$order = $this->get('sortorder');
			$name  = "fields[{$order}][settings][]";

			$crtDurSettings = $this->get('settings');

			$options = array();

			foreach ($this->durSettings as $durSetting) {
				$options[] = array(
					$durSetting,
					in_array($durSetting, $crtDurSettings),
					$durSetting
				);
			}

			$select = Widget::Select($name, $options, array('multiple' => 'multiple'));
			$label  = Widget::Label('Duration settings used', $select, 'column');

			$wrapper->appendChild($label);
		}

		public function commit() {
			if (!parent::commit()) {
				return false;
			}

			$id     = $this->get('id');
			$handle = $this->handle();

			if ($id === false) {
				return false;
			}

			// enforce seconds if the minimum is not met
			$durSettings = $this->get('settings');
			if (
				// no duration set
				empty($durSettings)
				// or fractions are set, without seconds
				|| (isset($durSettings['fractions']) && !isset($durSettings['seconds']))
			) {
				$durSettings[] = 'seconds';
			}

			$fields['field_id'] = $id;
			$fields['settings'] = implode(',', $durSettings);

			Symphony::Database()->query("DELETE FROM `tbl_fields_{$handle}` WHERE `field_id` = '{$id}' LIMIT 1");

			return Symphony::Database()->insert($fields, "tbl_fields_{$handle}");
		}

		public function appendFieldSchema(XMLElement $field){
			$settings = new XMLElement('settings');

			foreach ($this->get('settings') as $durSetting) {
				$settings->appendChild(new XMLElement($durSetting));
			}

			$field->appendChild($settings);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null, $entry_id = null) {
			if (!Symphony::Engine() instanceof Administration) {
				return;
			}

			$callback = Administration::instance()->getPageCallback();
			if ($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new') {
				return;
			}

			// add stylesheet
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/duration_field/assets/duration_field.publish.css', 'screen', 543, false);

			$element_name = $this->get('element_name');

			$container = new XMLElement('div');

			$label = Widget::Label($this->get('label'));
			if ($this->get('required') != 'yes') {
				$label->appendChild(new XMLElement('i', __('Optional')));
			}
			$container->appendChild($label);

			$data              = self::makeDuration($data['value'], $this->get('settings'));
			$settings          = $this->get('settings');
			$settingsContainer = new XMLElement('div', null, array('class' => 'settings'));

			// weeks, days, hours, minutes
			foreach ($this->durSettings as $durSetting) {
				if (
					(!in_array($durSetting, $settings))
					|| ($durSetting == 'seconds')
					|| ($durSetting == 'fractions')
				) {
					continue;
				}

				$label = Widget::Label();
				$label->setValue(__($durSetting), false);
				$input = Widget::Input("fields{$prefix}[$element_name][$durSetting]{$postfix}", (string) $data->{$durSetting});
				$label->appendChild($input);
				$settingsContainer->appendChild($label);
			}

			// seconds and fractions
			if (in_array('seconds', $settings)) {
				$label = Widget::Label();
				$input = Widget::Input("fields{$prefix}[$element_name][seconds]{$postfix}", (string) $data->seconds);
				$value = $input->generate();
				if (in_array('fractions', $settings)) {
					$input = Widget::Input("fields{$prefix}[$element_name][fractions]{$postfix}", (string) $data->fractions, 'text', array('class' => 'fractions'));
					$value .= ' . ' . $input->generate();
				}
				$label->setValue($value . __('seconds'));
				$settingsContainer->appendChild($label);
			}

			$container->appendChild($settingsContainer);

			if ($flagWithError != null) {
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else {
				$wrapper->appendChild($container);
			}
		}

		public function checkPostFieldData($data, &$message, $entry_id = null) {
			// duration settings
			if (is_array($data)) {

				// required
				if ($this->get('required') == 'yes' && empty($data)) {
					$message = __('‘%s’ is a required field.', array($this->get('label')));

					return self::__MISSING_FIELDS__;
				}

				// settings must be positive numbers or 0
				foreach ($this->durSettings as $durSetting) {
					if (!isset($data[$durSetting])) {
						continue;
					}

					if (!is_numeric($data[$durSetting]) || ($data[$durSetting] < 0)) {
						$message = __("Please provide positive numbers or 0.");

						return self::__INVALID_FIELDS__;
					}
				}
			}

			// duration
			else if (!is_numeric($data)) {
				$message = __("Please provide a positive number or 0.");

				return self::__INVALID_FIELDS__;
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null) {
			if (is_array($data)) {
				$duration = (object) array();

				foreach ($this->durSettings as $durSetting) {
					$duration->{$durSetting} = isset($data[$durSetting]) ? (int) $data[$durSetting] : 0;
				}

				$timestamp = self::makeTimestamp($duration);
			}
			else {
				$timestamp = $data;
			}

			return array(
				'value' => $timestamp
			);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			$result = new XMLElement($this->get('element_name'));

			// set the timestamp
			$result->setAttribute('timestamp', $data['value']);

			// set duration fields
			$duration = self::makeDuration($data['value'], $this->get('settings'));

			$settings = $this->get('settings');

			foreach ($this->durSettings as $durSetting) {
				if (!in_array($durSetting, $settings) || ($durSetting == 'fractions')) {
					continue;
				}

				$result->appendChild(new XMLElement($durSetting, $duration->{$durSetting}));
			}

			if (in_array('seconds', $settings) && in_array('fractions', $settings)) {
				$result->appendChild(new XMLElement('fractions', $duration->fractions));
			}

			$wrapper->appendChild($result);
		}

		public function getParameterPoolValue(array $data) {
			return $data['value'];
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			$duration = self::makeDuration($data['value'], $this->get('settings'));

			$settings = $this->get('settings');

			$value = array();

			foreach ($this->durSettings as $durSetting) {
				if (
					!in_array($durSetting, $settings)
					|| ($durSetting == 'seconds')
					|| ($durSetting == 'fractions')
				) {
					continue;
				}

				$value[] = $duration->{$durSetting} . __($durSetting[0]);
			}

			if (in_array('seconds', $settings)) {
				$v = $duration->seconds;

				if (in_array('fractions', $settings) && $duration->fractions > 0) {
					$v .= "." . $duration->fractions;
				}

				$value[] = $v . __("s");
			}

			return parent::prepareTableValue(array("value" => implode(" ", $value)), $link, $entry_id);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Sorting and filtering  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayDatasourceFilterPanel(&$wrapper, $data = null, $errors = null, $prefix = null, $postfix = null) {
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $prefix, $postfix);

			$text = new XMLElement('p', __('To filter by ranges, add <code>%s</code> to the beginning of the filter input. Use <code>%s</code> for field name. E.G. <code>%s</code>', array(
				'mysql:',
				'value',
				'mysql: value &gt;= 1600 AND value &lt;= {$some-param}'
			)), array('class' => 'help'));

			$wrapper->appendChild($text);
		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC') {
			$fieldId = $this->get('id');
			$isRand  = in_array(strtolower($order), array('random', 'rand'));

			// If we already have a JOIN to the entry table, don't create another one,
			// this prevents issues where an entry with multiple dates is returned multiple
			// times in the SQL, but is actually the same entry.
			if (!preg_match('/`t' . $fieldId . '`/', $joins)) {
				$joins .= "LEFT OUTER JOIN `tbl_entries_data_" . $fieldId . "` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
				$sort = 'ORDER BY ' . ($isRand ? 'RAND()' : "`ed`.`value` $order");
			}
			else {
				$sort = 'ORDER BY ' . ($isRand ? 'RAND()' : "`t$fieldId`.`value` $order");
			}
		}

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false) {

			// X to Y support
			if(preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+)) to (-?(?:\d+(?:\.\d+)?|\.\d+))$/i', $data[0], $match)) {

				$field_id = $this->get('id');

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.`value` BETWEEN {$match[1]} AND {$match[2]} ";

			}

			// Equal to or less/greater than X
			else if(preg_match('/^(equal to or )?(less|greater) than (-?(?:\d+(?:\.\d+)?|\.\d+))$/i', $data[0], $match)) {
				$field_id = $this->get('id');

				$expression = " `t$field_id`.`value` ";

				switch($match[2]) {
					case 'less':
						$expression .= '<';
						break;

					case 'greater':
						$expression .= '>';
						break;
				}

				if($match[1]){
					$expression .= '=';
				}

				$expression .= " {$match[3]} ";

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND $expression ";

			}

			else {
				parent::buildDSRetrievalSQL($data, $joins, $where, $andOperation);
			};

			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Build duration from given timestamp.
		 *
		 * @param double $timestamp
		 * @param array  $settings - settings to respect
		 *
		 * @return object {weeks, days, hours, minutes, seconds, fractions}
		 */
		public static function makeDuration($timestamp, $settings = array()) {
			if (strpos($timestamp, ".")) {
				list($whole, $fractions) = explode(".", $timestamp);
			}
			else {
				$whole     = $timestamp;
				$fractions = 0;
			}

			$duration = (object) array();

			$duration->seconds = $whole % 60;
			$whole             = (int) ($whole / 60);

			$duration->minutes = $whole % 60;
			$whole             = (int) ($whole / 60);

			$duration->hours = $whole % 24;
			$whole           = (int) ($whole / 24);

			$duration->days      = $whole % 7;
			$duration->weeks     = (int) ($whole / 7);
			$duration->fractions = $fractions !== null ? (int) $fractions : 0;

			// if settings must be respected, propagate results to where necessary
			if (!empty($settings)) {
				$use_weeks     = in_array('weeks', $settings);
				$use_days      = in_array('days', $settings);
				$use_hours     = in_array('hours', $settings);
				$use_minutes   = in_array('minutes', $settings);
				$use_seconds   = in_array('seconds', $settings);
				$use_fractions = in_array('fractions', $settings);

				if (!$use_weeks) {
					$duration->days += $duration->weeks * 7;
					$duration->weeks = 0;
				}

				if (!$use_days) {
					$duration->hours += $duration->days * 24;
					$duration->days = 0;
				}

				if (!$use_hours) {
					$duration->minutes += $duration->hours * 60;
					$duration->hours = 0;
				}

				if (!$use_minutes) {
					$duration->seconds += $duration->minutes * 60;
					$duration->minutes = 0;
				}

				if (!$use_seconds) {
					$duration->seconds = 0;
				}

				if (!$use_seconds || !$use_fractions) {
					$duration->fractions = 0;
				}
			}

			return $duration;
		}

		/**
		 * Make timestamp from given duration.
		 *
		 * @see makeDuration() for informtion about $duration structure
		 *
		 * @param array|object $duration
		 *
		 * @return float
		 */
		public static function makeTimestamp($duration) {
			if (!is_object($duration)) {
				$duration = (object) $duration;
			}

			$timestamp =
				$duration->weeks * 7 * 24 * 60 * 60
				+ $duration->days * 24 * 60 * 60
				+ $duration->hours * 60 * 60
				+ $duration->minutes * 60
				+ $duration->seconds;

			if ($duration->fractions > 0) {
				$timestamp = doubleval("$timestamp.$duration->fractions");
			}

			return $timestamp;
		}
	}
