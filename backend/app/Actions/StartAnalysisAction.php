<?php

namespace App\Actions;

use App\Exceptions\AnalysisException;
use App\Jobs\AnalyzeSequenceJob;
use App\Models\AnalysisJob;
use App\Models\AnalysisJobItem;
use App\Models\Script;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Lance l'analyse IA d'un script (L-06).
 *
 * Crée un AnalysisJob + un AnalysisJobItem par séquence, puis dispatch un
 * AnalyzeSequenceJob par item sur la file 'ai'. Le dispatch a lieu APRÈS le
 * commit pour qu'un worker ne saisisse pas un item avant que ses lignes soient
 * visibles. Multi-tables → transaction.
 */
class StartAnalysisAction
{
    public function execute(Script $script, string $userId): AnalysisJob
    {
        if ($script->parse_status !== 'parsed') {
            throw AnalysisException::scriptNotParsed();
        }

        if ($this->hasActiveJob($script)) {
            throw AnalysisException::alreadyRunning();
        }

        $sequences = Sequence::where('script_id', $script->id)
            ->orderBy('display_order')
            ->get();

        if ($sequences->isEmpty()) {
            throw AnalysisException::noSequences();
        }

        $job = DB::transaction(function () use ($script, $userId, $sequences): AnalysisJob {
            $job = AnalysisJob::create([
                'project_id' => $script->project_id,
                'script_id' => $script->id,
                'requested_by' => $userId,
                'status' => 'pending',
                'total_sequences' => $sequences->count(),
            ]);

            foreach ($sequences as $sequence) {
                AnalysisJobItem::create([
                    'project_id' => $script->project_id,
                    'analysis_job_id' => $job->id,
                    'sequence_id' => $sequence->id,
                    'status' => 'pending',
                ]);
            }

            return $job;
        });

        foreach ($job->items()->pluck('id') as $itemId) {
            AnalyzeSequenceJob::dispatch($itemId);
        }

        return $job;
    }

    /**
     * Vrai si une analyse est déjà en cours (pending/running) pour ce script.
     */
    private function hasActiveJob(Script $script): bool
    {
        return AnalysisJob::where('script_id', $script->id)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }
}
