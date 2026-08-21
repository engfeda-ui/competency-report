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
 * Assessment setup interactions module.
 *
 * @module     local_comp_report_ext/assessment_setup
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Toggles quiz vs assignment selector fields based on selected assessment type.
     *
     * @param {string} val Selected assessment type ('quiz' or 'practical').
     */
    var toggleQuizField = function(val) {
        var quizWrap = document.getElementById('quiz-selector-wrap');
        var assignWrap = document.getElementById('assign-selector-wrap');
        if (!quizWrap || !assignWrap) {
            return;
        }
        if (val === 'practical') {
            quizWrap.style.display = 'none';
            assignWrap.style.display = '';
        } else {
            quizWrap.style.display = '';
            assignWrap.style.display = 'none';
        }
    };

    /**
     * Auto-fills the assessment name input if empty when an activity is selected.
     *
     * @param {HTMLSelectElement} selectEl
     */
    var autoFillName = function(selectEl) {
        var nameInput = document.getElementById('new_name');
        if (!nameInput || !selectEl) {
            return;
        }
        if (selectEl.value !== '0' && nameInput.value.trim() === '') {
            var selectedText = selectEl.options[selectEl.selectedIndex].text;
            if (selectedText.indexOf('—') === 0) {
                return;
            }
            nameInput.value = selectedText;
        }
    };

    return {
        /**
         * Initialize event listeners for the assessment setup form.
         */
        init: function() {
            var typeSelect = document.getElementById('new_type');
            var quizSelect = document.getElementById('new_quizid');
            var assignSelect = document.getElementById('new_assignid');

            if (typeSelect) {
                typeSelect.addEventListener('change', function() {
                    toggleQuizField(this.value);
                });
                toggleQuizField(typeSelect.value);
            }

            if (quizSelect) {
                quizSelect.addEventListener('change', function() {
                    autoFillName(this);
                });
            }

            if (assignSelect) {
                assignSelect.addEventListener('change', function() {
                    autoFillName(this);
                });
            }
        }
    };
});
