import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChartController from '@symfony/ux-chartjs/controller';
import {Chart, Colors} from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

const app = startStimulusApp();
ChartDataLabels.defaults.formatter = function(value, ctx) {
    // Va chercher le label correspondant à la part du donut
    return ctx.chart.data.labels[ctx.dataIndex];
};
Chart.register(Colors, ChartDataLabels);
window.ChartDataLabels = ChartDataLabels;
app.register('symfony--ux-chartjs--chart', ChartController);
