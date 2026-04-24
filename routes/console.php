<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('music:sync-catalog')->hourly();
