<?php

	Class extension_System_Date_Field extends Extension{
	
		public function about(){
			return array('name' => 'Field: System Date',
						 'version' => '0.1',
						 'release-date' => '2009-08-05',
						 'author' => array('name' => 'Alistair Kearney',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'alistair@symphony-cms.com')
				 		);
		}
		
		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_systemdate`");
		}
		

		public function install(){
			return Symphony::Database()->query("CREATE TABLE `tbl_fields_systemdate` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
			  PRIMARY KEY  (`id`),
			  KEY `field_id` (`field_id`)
			)");
		}
			
	}

