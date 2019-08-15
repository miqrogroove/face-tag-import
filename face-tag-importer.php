<?php
/**
 * Face Tag Import Script
 *
 * @author: Robert Chapin
 * @copyright 2019 by Robert Chapin
 * @license GPL
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

define( 'IMPORT_FILE_PATH', 'tree.ged' );
define( 'WT_SCRIPT_NAME', 'face-tag-importer.php' );
require './includes/session.php';

if ( ! Fisharebest\Webtrees\Auth::isAdmin() ) {
	exit ('You must be logged in as an admin to use this page.');
}

if ( ! is_readable( IMPORT_FILE_PATH ) ) {
	exit ('Unable to read file ' . IMPORT_FILE_PATH);
}

$gedcom_file = file_get_contents( IMPORT_FILE_PATH );

// BEGIN FTB7 extraction.

//split file on pattern '\r\n0 '

$root_records = preg_split( '#\r\n0 #', $gedcom_file );
$root_count = count( $root_records ) - 1;

echo "Found $root_count root records.<br>";

unset( $gedcom_file );

//Then throw out any strings that don't begin with '\r\n0 @I\d@ INDI'

foreach ( $root_records as $key => $record ) {
	if ( 1 !== preg_match( '#^@I\\d+@ INDI\\r\\n#', $record ) ) {
		unset( $root_records[$key] );
	}
}

//For each INDI record:

/* Match pattern...
2 _POSITION 278 62 443 281
2 _PHOTO_RIN MH:P39

but also

2 _POSITION 352 306 552 573
2 _ALBUM @A9@
2 _PHOTO_RIN MH:P2390
*/

$face_tags = array();
$indi_count = count( $root_records );
$tag_count = 0;

echo "Found $indi_count total individuals in the tree.<br>";

foreach ( $root_records as $record ) {
	if ( false === strpos( $record, '_POSITION' ) ) {
		continue;
	}

	$id_end = strpos( $record, '@', 2 );
	if ( false === $id_end ) {
		continue;
	}
	$id = substr( $record, 1, $id_end - 1 );
		
	//split record on pattern '\r\n1 '

	$obje_records = preg_split( '#\r\n1 #', $record );

	//Then throw out any strings that don't begin with '\r\n1 OBJE'

	foreach ( $obje_records as $key => $obje ) {
		if ( 'OBJE' !== substr( $obje, 0, 4 ) || false === strpos( $obje, "\r\n2 _POSITION" ) || false === strpos( $obje, "\r\n2 _PHOTO_RIN" ) ) {
			unset( $obje_records[$key] );
		}
	}

	foreach ( $obje_records as $obje ) {
		$matches = array();
		if ( 1 === preg_match( '#\\r\\n2 _POSITION (\\d+) (\\d+) (\\d+) (\\d+)\\r\\n#', $obje, $matches ) ) {
		
			/* Expected $matches like...
				array(5) {
				  [0]=>
				  string(31) "
				2 _POSITION 352 306 552 573
				"
				  [1]=>
				  string(3) "352"
				  [2]=>
				  string(3) "306"
				  [3]=>
				  string(3) "552"
				  [4]=>
				  string(3) "573"
				}		
			*/
		
			$coords = array( $matches[1], $matches[2], $matches[3], $matches[4] );
			
		} else {
			continue;
		}
			
		$matches = array();
		if ( 1 === preg_match( '#\\r\\n2 _PHOTO_RIN (MH:P\\d+)\\r\\n#', $obje, $matches ) ) {

			/* Expected $matches like...
				array(2) {
				  [0]=>
				  string(25) "
				2 _PHOTO_RIN MH:P2390
				"
				  [1]=>
				  string(8) "MH:P2390"
				}
			*/
			
			// Save the INDI reference and the _POSITION data into an array indexed by the _PHOTO_RIN.
			
			$face_tags[$matches[1]][$id] = $coords;
			
			$tag_count++;
		}
	}
}

unset( $root_records, $record, $obje_records, $obje );

$photo_count = count( $face_tags );
echo "Found $tag_count face tags in $photo_count photos.<br>";


// Extract all _PHOTO_RIN values from webtrees gedcom values.
// This will allow each FTB face tag to be associated with a webtrees media
// object using a simple array.

// mysql> SELECT `m_id` FROM `wt_media` WHERE `m_gedcom` LIKE '%\n1 \\_PHOTO\\_RIN MH:P44\n%';
// $sql = "SELECT `m_id` FROM `wt_media` WHERE `m_gedcom` LIKE '%\\n1 \\\\_PHOTO\\\\_RIN MH:P44\\n%'";

$id_to_rin = array();

$media = Fisharebest\Webtrees\Database::prepare(
	"SELECT `m_id`, `m_gedcom` " .
	"FROM `##media` "
)->fetchAssoc();
	
foreach ( $media as $id => $gedcom ) {
	$matches = array();
	if ( 1 === preg_match( '#\\n1 _PHOTO_RIN (MH:P\\d+)\\n#', $gedcom, $matches ) ) {

		/* Expected $matches like...
			array(2) {
			  [0]=>
			  string(25) "
			1 _PHOTO_RIN MH:P2390
			"
			  [1]=>
			  string(8) "MH:P2390"
			}
		*/

		$id_to_rin[$id] = $matches[1];
	}
}

unset( $media );


// END FTB7 extraction.


// BEGIN WT import.

// INSERT the _POSITION record(s) for this photo.

/* mysql> SELECT * FROM wt_photo_notes;
+----------+------------------------------------------------------------------------------------------------------+------------+--------------------+
| pnwim_id | pnwim_coordinates                                                                                    | pnwim_m_id | pnwim_m_filename   |
+----------+------------------------------------------------------------------------------------------------------+------------+--------------------+
|        1 | [{"pid":"I1","coords":["308","178","473","395"]},{"pid":"I3164","coords":["191","213","354","428"]}] | M74641     | P2015_699_1048.jpg |
+----------+------------------------------------------------------------------------------------------------------+------------+--------------------+
*/

// For each _PHOTO_RIN:

$db_queue = array();
$empty_counter = 0;

foreach ( $face_tags as $rin => $links ) {

	// Find the database record for this photo.

	$media_id = array_search( $rin, $id_to_rin );
	
	if ( false === $media_id ) {
		echo 'The $media_id was not found for RIN ' . $rin . '.<br>';
		$empty_counter++;
		continue;
	}
	
	// Generate the JSON string for this photo.

	$plugin_data = array();
	foreach ( $links as $id => $coords ) {
		$plugin_data[] = array( 'pid' => $id, 'coords' => $coords );
	}
	$db_queue[$media_id] = json_encode( $plugin_data );
}

unset( $face_tags, $id_to_rin );

echo '$db_queue array contains ' . count( $db_queue ) . ' entries.<br>';

// For each photo, convert the JSON string to a SQL string.

$values = array();
foreach ( $db_queue as $media_id => $plugin_data ) {
	$values[] = '(' . Fisharebest\Webtrees\Database::quote( $media_id ) . ', ' . Fisharebest\Webtrees\Database::quote( $plugin_data ) . ', "")';
}

unset( $db_queue );

// Now build the INSERT statement and use it to add all face tag records to the database.

$values = implode( ',', $values );

echo 'Query size is ' . strlen( $values ) . '.<br>';

Fisharebest\Webtrees\Database::prepare(
	"TRUNCATE ##photo_notes"
)->execute();
Fisharebest\Webtrees\Database::prepare(
	"INSERT INTO ##photo_notes (pnwim_m_id, pnwim_coordinates, pnwim_m_filename) " .
	"VALUES " . $values . " "
)->execute();

unset( $values );

// END WT import.


echo 'Done.<br><a href="../">Return Home</a>';

return;
?>