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
 * Modal viewer for competency question review.
 *
 * @module     local_yetkinlik/modal_viewer
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str', 'core/notification'],
    function($, ModalFactory, ModalEvents, Str, Notification) {

    return {
        /**
         * Initialize the modal viewer for the given selector.
         *
         * @param {string} selector The CSS selector for the links (e.g., '.view-question-modal')
         */
        init: function(selector) {
            $(selector).on('click', function(e) {
                // Prevent the default link behavior (opening in a new tab).
                e.preventDefault();

                var targetUrl = $(this).attr('href');

                // Fetch the translated string for the modal title.
                // We use 'viewattempt' from our local_yetkinlik language file.
                Str.get_string('viewattempt', 'local_yetkinlik').then(function(title) {

                    // Create the Moodle Modal instance.
                    return ModalFactory.create({
                        type: ModalFactory.types.CANCEL,
                        title: title,
                        body: '<iframe src="' + targetUrl + '" width="100%" height="600px" frameborder="0"></iframe>',
                        large: true
                    });

                }).then(function(modal) {
                    // Display the modal to the user.
                    modal.show();

                    // Destroy the modal from the DOM once it is hidden to free up memory.
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });

                    return;
                }).catch(Notification.exception);
            });
        }
    };
});