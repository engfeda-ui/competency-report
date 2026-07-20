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
 * Chart visualizations for the local_comp_report_ext plugin.
 *
 * @module      local_comp_report_ext/visualizer
 * @copyright   2026 Mahmoud Salem
 * @copyright   based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ChartJS from 'core/chartjs';

/**
 * Destroy any existing Chart instance on a canvas before creating a new one.
 *
 * @param {HTMLCanvasElement} canvas
 * @returns {void}
 */
const destroyExisting = (canvas) => {
    const existing = ChartJS.getChart(canvas);
    if (existing) {
        existing.destroy();
    }
};

/**
 * Initialize Student vs Class/Course comparison bar chart.
 *
 * @param {Object|Array} params Chart data parameters.
 */
export const initStudentClass = (params) => {
    const data = Array.isArray(params) ? params[0] : params;
    const canvas = document.getElementById('studentClassChart');
    if (!canvas || !data) {
        return;
    }
    destroyExisting(canvas);
    new ChartJS(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {label: data.labelNames.course, data: data.courseData, backgroundColor: 'rgba(156, 39, 176, 0.4)'},
                {label: data.labelNames.class, data: data.classData, backgroundColor: 'rgba(76, 175, 80, 0.4)'},
                {label: data.labelNames.my, data: data.myData, backgroundColor: 'rgba(33, 150, 243, 0.8)'},
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {y: {beginAtZero: true, max: 100}},
        },
    });
};

/**
 * Initialize individual student exam analysis bar chart.
 *
 * @param {Object|Array} params Chart data parameters.
 */
export const initStudentExam = (params) => {
    const data = Array.isArray(params) ? params[0] : params;
    const canvas = document.getElementById('studentexamchart');
    if (!canvas || !data) {
        return;
    }
    destroyExisting(canvas);
    new ChartJS(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{label: data.chartLabel, data: data.chartData, backgroundColor: data.bgColors}],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {y: {beginAtZero: true, max: 100}},
        },
    });
};

/**
 * Initialize competency progress timeline line chart.
 *
 * @param {Object|Array} params Chart data parameters.
 */
export const initTimeline = (params) => {
    const data = Array.isArray(params) ? params[0] : params;
    const canvas = document.getElementById('timeline');
    if (!canvas || !data) {
        return;
    }
    destroyExisting(canvas);
    new ChartJS(canvas.getContext('2d'), {
        type: 'line',
        data: {labels: data.labels, datasets: data.datasets},
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {display: true, text: data.successLabel + ' (%)'},
                },
            },
            plugins: {legend: {position: 'bottom'}},
        },
    });
};

/**
 * General purpose bar chart initializer.
 *
 * @param {string} elementId Canvas element ID.
 * @param {Array}  labels    X-axis labels.
 * @param {Array}  data      Dataset values.
 * @param {string} labelText Dataset label.
 */
export const initBarChart = (elementId, labels, data, labelText) => {
    const canvas = document.getElementById(elementId);
    if (!canvas) {
        return;
    }
    destroyExisting(canvas);
    new ChartJS(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{label: labelText, data, backgroundColor: '#ff9800', borderWidth: 1}],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {y: {beginAtZero: true, max: 100}},
        },
    });
};
