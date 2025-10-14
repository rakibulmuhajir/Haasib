<?php

namespace Modules\Accounting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Services\RedisCacheService;

abstract class BaseAccountingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The company ID this job belongs to.
     */
    protected string $companyId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Set the company ID for this job.
     *
     * @return $this
     */
    public function setCompanyId(string $companyId)
    {
        $this->companyId = $companyId;

        return $this;
    }

    /**
     * Execute the job with proper company context.
     *
     * @return void
     */
    public function handle()
    {
        $cacheService = app(RedisCacheService::class);

        $cacheService->withCompanyContext($this->companyId, function () {
            $this->execute();
        });
    }

    /**
     * Execute the job logic.
     *
     * This method should be implemented by child classes.
     * The company context is already set when this method is called.
     */
    abstract protected function execute(): void;

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'accounting',
            'company:'.$this->companyId,
            static::class,
        ];
    }
}
