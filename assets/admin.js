import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChartController from '@symfony/ux-chartjs/controller';

const app = startStimulusApp();
app.register('symfony--ux-chartjs--chart', ChartController);
