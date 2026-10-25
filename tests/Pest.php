<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configurazione dei test
|--------------------------------------------------------------------------
| Feature: applicazione completa con database MySQL di test (ripulito a ogni test).
| Unit: classi isolate, senza applicazione.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
