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
 * Graphic drawing for the local_yetkinlik plugin.
 *
 * @module      local_yetkinlik/charts
 * @copyright   2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/chartjs'], function($, Chart) {
    "use strict";

    return {
        /**
         * Initialize Student vs Class/Course comparison chart.
         *
         * @param {Object|Array} params The data parameters for the chart.
         */
        initStudentClass: function(params) {
            var data = Array.isArray(params) ? params[0] : params;
            var canvas = document.getElementById('studentClassChart');

            if (!canvas || !data) {
                return;
            }

            var existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: data.labelNames.course,
                            data: data.courseData,
                            backgroundColor: 'rgba(156, 39, 176, 0.4)'
                        },
                        {
                            label: data.labelNames.class,
                            data: data.classData,
                            backgroundColor: 'rgba(76, 175, 80, 0.4)'
                        },
                        {
                            label: data.labelNames.my,
                            data: data.myData,
                            backgroundColor: 'rgba(33, 150, 243, 0.8)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },

        /**
         * Initialize individual student exam analysis chart.
         *
         * @param {Object|Array} params The data parameters for the chart.
         */
        initStudentExam: function(params) {
            var data = Array.isArray(params) ? params[0] : params;
            var canvas = document.getElementById('studentexamchart');

            if (!canvas || !data) {
                return;
            }

            var existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: data.chartLabel,
                        data: data.chartData,
                        backgroundColor: data.bgColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },

        /**
         * Initialize timeline (progress) chart.
         *
         * @param {Object|Array} params The data parameters for the chart.
         */
        initTimeline: function(params) {
            var data = Array.isArray(params) ? params[0] : params;
            var canvas = document.getElementById('timeline');

            if (!canvas || !data) {
                return;
            }

            var existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: data.successLabel + ' (%)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        /**
         * General purpose bar chart initializer for Teacher Dashboard.
         *
         * @param {string} elementId The ID of the canvas element.
         * @param {Array} labels The labels for the x-axis.
         * @param {Array} data The dataset values.
         * @param {string} labelText The label for the dataset.
         */
        initBarChart: function(elementId, labels, data, labelText) {
            var canvas = document.getElementById(elementId);

            if (!canvas) {
                return;
            }

            var existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: labelText,
                        data: data,
                        backgroundColor: '#ff9800',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    };
});