<?php

use App\Contracts\NewsSourceContract;
use App\Enums\NewsSourceEnum;
use App\Jobs\FetchArticlesFromSourceJob;
use App\Services\NewsAggregator\NewsAggregatorService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    app()->singleton(NewsAggregatorService::class, fn () => new NewsAggregatorService);
});

function registerFakeSource(NewsSourceEnum $identifier = NewsSourceEnum::GUARDIAN): void
{
    $source = Mockery::mock(NewsSourceContract::class);
    $source->shouldReceive('getIdentifier')->andReturn($identifier);
    $source->shouldReceive('getSourceName')->andReturn($identifier->label());
    $source->shouldReceive('isConfigured')->andReturn(true);

    app(NewsAggregatorService::class)->addNewsSource($source);
}

it('dispatches jobs for all configured sources', function () {
    Queue::fake();
    registerFakeSource(NewsSourceEnum::GUARDIAN);
    registerFakeSource(NewsSourceEnum::NEW_YORK_TIMES);

    $this->artisan('news:fetch')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 2 fetch job(s)');

    Queue::assertPushed(FetchArticlesFromSourceJob::class, 2);
});

it('filters by --source option', function () {
    Queue::fake();
    registerFakeSource(NewsSourceEnum::GUARDIAN);
    registerFakeSource(NewsSourceEnum::NEW_YORK_TIMES);

    $this->artisan('news:fetch --source=guardian')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 1 fetch job(s)');

    Queue::assertPushed(FetchArticlesFromSourceJob::class, 1);
});

it('fails with invalid source option', function () {
    registerFakeSource();

    $this->artisan('news:fetch --source=invalid')
        ->assertFailed()
        ->expectsOutputToContain('Invalid source');
});

it('fails when no sources are configured', function () {
    $this->artisan('news:fetch')
        ->assertFailed()
        ->expectsOutputToContain('No configured news sources');
});

it('fails when specified source is not configured', function () {
    registerFakeSource(NewsSourceEnum::GUARDIAN);

    $this->artisan('news:fetch --source=new_york_times')
        ->assertFailed()
        ->expectsOutputToContain('not configured');
});
