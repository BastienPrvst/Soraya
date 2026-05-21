import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChartController from '@symfony/ux-chartjs/controller';
import {Chart, Colors} from 'chart.js';


const app = startStimulusApp();
Chart.register(Colors);

app.register('symfony--ux-chartjs--chart', ChartController);
