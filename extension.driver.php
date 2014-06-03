<?php

	Class Extension_Duration_Field extends Extension {

		public $field_table = 'tbl_fields_duration';



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install() {
			return Symphony::Database()->query(sprintf(
				"CREATE TABLE `%s` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`settings` text DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				$this->field_table
			));
		}

		public function uninstall() {
			try {
				Symphony::Database()->query(sprintf(
					"DROP TABLE `%s`",
					$this->field_table
				));
			} catch (DatabaseException $dbe) {
				// table doesn't exist
			}
		}
	}
