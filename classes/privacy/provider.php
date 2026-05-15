<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Local plugin "yetkinlik" - Privacy provider.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_yetkinlik\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadataprovider;

/**
 * Privacy Subsystem implementation.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadataprovider {
    /**
     * Returns metadata about the data sent to external locations.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The modified collection.
     */
    public static function get_metadata(collection $collection): collection {
        // We declare that data has been sent to the OpenAI API.
        $collection->add_external_location_link(
            'openai',
            [
                'questiontext' => 'privacy:metadata:openai:questiontext',
                'answertext' => 'privacy:metadata:openai:answertext',
            ],
            'privacy:metadata:openai:externalpurpose'
        );

        return $collection;
    }
}
