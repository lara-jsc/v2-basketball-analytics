<?php

/*
|--------------------------------------------------------------------------
| Pest Test Suite Bootstrap
|--------------------------------------------------------------------------
|
| Feature tests run inside the full Laravel application, so they extend
| the project's base TestCase. Unit tests are pure-PHP (no framework boot)
| and do not carry this trait.
|
*/

uses(Tests\TestCase::class)->in('Feature');
