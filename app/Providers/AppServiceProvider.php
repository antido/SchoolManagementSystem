<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // While developing and testing, throw an error instead of quietly
        // running one query per row (lazy loading in a loop) or quietly
        // ignoring a field that is not fillable.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->addSearchHelpers();
        $this->logSlowQueries();
    }

    /**
     * Write any query slower than config('school.slow_query_ms') to the log,
     * with its SQL and the page it ran for, so slow spots show up in real use.
     */
    private function logSlowQueries(): void
    {
        $threshold = (int) config('school.slow_query_ms');

        if ($threshold <= 0) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($threshold): void {
            if ($query->time >= $threshold) {
                Log::warning('Slow query', [
                    'ms' => $query->time,
                    'sql' => $query->sql,
                    'url' => $this->app->runningInConsole() ? 'console' : request()->fullUrl(),
                ]);
            }
        });
    }

    /**
     * Add ->whereContains('name', $text) and ->orWhereContains(...) to every
     * query: rows where the column contains the text.
     *
     * The text is sent as a bound parameter (never as SQL), and the LIKE
     * wildcards % and _ are escaped, so they match themselves. Works on
     * MySQL and on the SQLite database the tests use.
     */
    private function addSearchHelpers(): void
    {
        Builder::macro('whereContains', function (string $column, string $text, string $boolean = 'and') {
            /** @var Builder $this */
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';

            return $this->whereRaw($this->grammar->wrap($column)." like ? escape '!'", [$pattern], $boolean);
        });

        Builder::macro('orWhereContains', function (string $column, string $text) {
            /** @var Builder $this */
            return $this->whereContains($column, $text, 'or');
        });
    }
}
