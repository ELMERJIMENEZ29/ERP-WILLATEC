<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('licencias:enviar-alertas')
    ->dailyAt('10:00')
    ->timezone('America/Lima')
    ->withoutOverlapping();

Schedule::command('servicios:procesar-renovaciones')
    ->dailyAt('00:10')
    ->timezone('America/Lima')
    ->withoutOverlapping();
