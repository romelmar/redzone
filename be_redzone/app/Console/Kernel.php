<?php
// app/Console/Kernel.php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        // Logic to check subscriptions due for payment and deduct automatically
    })->daily();
}
