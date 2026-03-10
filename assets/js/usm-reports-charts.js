/**
 * USM Reports – Chart.js charts.
 *
 * Expects `usmReportData` to be localized via wp_localize_script.
 *
 * @package USM
 */

/* global Chart, usmReportData */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined' || typeof usmReportData === 'undefined') return;

        // Revenue trend bar chart.
        var revEl = document.getElementById('usm-report-revenue');
        if (revEl) {
            new Chart(revEl, {
                type: 'bar',
                data: {
                    labels: usmReportData.months,
                    datasets: [{
                        label: 'Doanh thu',
                        data: usmReportData.revenue,
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

        // Enrollment status doughnut.
        var statusEl = document.getElementById('usm-report-status');
        if (statusEl) {
            new Chart(statusEl, {
                type: 'doughnut',
                data: {
                    labels: usmReportData.statusLabels,
                    datasets: [{
                        data: usmReportData.statusData,
                        backgroundColor: ['#2271b1', '#dba617', '#00a32a', '#d63638'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    });
})();
