import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['kdaChart', 'heatmapChart'];

    static values = {
        autoRefresh: { type: Boolean, default: false },
        refreshInterval: { type: Number, default: 90000 },
        chartLabels: Array,
        kills: Array,
        deaths: Array,
        assists: Array,
    };

    connect() {
        this.renderKdaChart();
        this.renderHeatmapChart();

        if (this.autoRefreshValue && window.Turbo) {
            this.refreshTimer = window.setInterval(() => {
                window.Turbo.visit(window.location.href, { action: 'replace' });
            }, this.refreshIntervalValue);
        }
    }

    disconnect() {
        if (this.refreshTimer) {
            window.clearInterval(this.refreshTimer);
        }

        if (this.kdaChart) {
            this.kdaChart.destroy();
        }

        if (this.heatmapChart) {
            this.heatmapChart.destroy();
        }
    }

    renderKdaChart() {
        if (!this.hasKdaChartTarget || typeof Chart === 'undefined') {
            return;
        }

        const labels = this.chartLabelsValue || [];
        const kills = this.killsValue || [];
        const deaths = this.deathsValue || [];
        const assists = this.assistsValue || [];

        this.kdaChart = new Chart(this.kdaChartTarget, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Kills', data: kills, backgroundColor: 'rgba(75, 192, 192, 0.7)' },
                    { label: 'Deaths', data: deaths, backgroundColor: 'rgba(255, 99, 132, 0.7)' },
                    { label: 'Assists', data: assists, backgroundColor: 'rgba(54, 162, 235, 0.7)' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }

    renderHeatmapChart() {
        if (!this.hasHeatmapChartTarget || typeof Chart === 'undefined') {
            return;
        }

        const labels = this.chartLabelsValue || [];
        const kills = this.killsValue || [];

        this.heatmapChart = new Chart(this.heatmapChartTarget, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Impact Kills',
                    data: kills,
                    fill: true,
                    tension: 0.35,
                    borderColor: 'rgba(153, 102, 255, 1)',
                    backgroundColor: 'rgba(153, 102, 255, 0.25)',
                    pointRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }
}
