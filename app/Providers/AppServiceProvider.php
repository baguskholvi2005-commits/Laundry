<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\ActivityObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ([Transaction::class, Payment::class, Expense::class, Service::class, Promo::class, Customer::class, User::class] as $model) {
            $model::observe(ActivityObserver::class);
        }
    }
}