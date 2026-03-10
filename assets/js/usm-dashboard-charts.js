/**
 * USM Dashboard – Chart.js charts.
 *
 * Expects `usmChartData` to be localized via wp_localize_script.
 *
 * @package USM
 */

/* global Chart, usmChartData */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined' || typeof usmChartData === 'undefined') return;

        // Revenue bar chart.
        var revEl = document.getElementById('usm-revenue-chart');
        if (revEl) {
            new Chart(revEl, {
                type: 'bar',
                data: {
                    labels: usmChartData.months,
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: usmChartData.revenue,
                        backgroundColor: 'rgba(34, 113, 177, 0.7)',
                        borderColor: '#2271b1',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return new Intl.NumberFormat('vi-VN').format(ctx.parsed.y) + ' ₫';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (v) {
                                    return (v / 1000000).toFixed(1) + 'tr';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Students line chart.
        var stuEl = document.getElementById('usm-students-chart');
        if (stuEl) {
            new Chart(stuEl, {
                type: 'line',
                data: {
                    labels: usmChartData.months,
                    datasets: [{
                        label: 'Học viên mới',
                        data: usmChartData.students,
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 5,
                        pointBackgroundColor: '#00a32a'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
    });
})();
